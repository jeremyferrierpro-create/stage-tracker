<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Authorization\EffectivePermissions;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Reporting\Models\Report;
use App\Domain\Reporting\Models\ReportTemplate;
use App\Domain\Reporting\Models\ReportTemplateVersion;
use App\Jobs\GenerateReport;
use Dompdf\Dompdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Reader\Html;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rapports de stage (T-063) : génération asynchrone (permission `export`,
 * 🔶 supervisor/tutor via octroi JSON), liste et téléchargement contrôlé
 * du fichier stocké sur le disque privé (INV-14 : contenu filtré).
 */
class ReportController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, Workspace $workspace, string $internship): View
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);

        $permissions = EffectivePermissions::resolveForInternship($request->user(), $workspace, $internshipModel);
        abort_unless($permissions['view_internship'], 403);

        $user = $request->user();

        return view('internships.reports', [
            'workspace' => $workspace,
            'internship' => $internshipModel,
            'reports' => $internshipModel->reports()
                ->with(['templateVersion.template', 'generator'])
                ->orderByDesc('generated_at')
                ->get(),
            // Uniquement des versions publiées (immuables) pour générer.
            'templates' => ReportTemplate::query()
                ->where('workspace_id', $workspace->id)
                ->where('status', 'published')
                ->with(['versions' => fn ($query) => $query->whereNotNull('published_at')])
                ->orderBy('name')
                ->get(),
            'canGenerate' => $permissions['export'] || in_array($workspace->roleFor($user), ['owner', 'admin'], true),
        ]);
    }

    public function store(Request $request, Workspace $workspace, string $internship): RedirectResponse
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);

        $permissions = EffectivePermissions::resolveForInternship($request->user(), $workspace, $internshipModel);
        abort_unless($permissions['export'], 403);

        $validated = $request->validate([
            'template_version_id' => ['required', 'uuid'],
        ]);

        // La version doit être publiée et appartenir à un modèle de l'espace.
        $version = ReportTemplateVersion::query()
            ->whereHas('template', fn ($query) => $query->where('workspace_id', $workspace->id))
            ->whereNotNull('published_at')
            ->findOrFail($validated['template_version_id']);

        $report = Report::query()->create([
            'internship_id' => $internshipModel->id,
            'workspace_id' => $workspace->id,
            'template_version_id' => $version->id,
            'generated_by' => $request->user()->id,
            'generated_at' => now(),
            'status' => 'queued',
        ]);

        $this->audit->record(
            'report.requested',
            $workspace,
            $report,
            ['status' => 'queued'],
            $request->user(),
        );

        GenerateReport::dispatch($report);

        return redirect()
            ->back()
            ->with('status', 'Rapport en file de génération.');
    }

    public function download(Request $request, Workspace $workspace, string $internship, string $report): StreamedResponse
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);

        $permissions = EffectivePermissions::resolveForInternship($request->user(), $workspace, $internshipModel);
        abort_unless($permissions['view_internship'], 403);

        $reportModel = $internshipModel->reports()->where('id', $report)->firstOrFail();

        abort_if($reportModel->status !== 'generated' || $reportModel->file_path === null, 404);

        $this->audit->record(
            'report.downloaded',
            $workspace,
            $reportModel,
            ['subject' => 'report'],
            $request->user(),
        );

        return Storage::disk('local')->download($reportModel->file_path, $this->filename($reportModel));
    }

    /**
     * Export PDF/DOCX (T-064) : rendu à partir du rapport généré, fichier
     * stocké sur le disque privé, téléchargement contrôlé et audité.
     */
    public function export(Request $request, Workspace $workspace, string $internship, string $report, string $format): StreamedResponse
    {
        abort_unless(in_array($format, ['pdf', 'docx'], true), 404);

        $internshipModel = $workspace->internships()->findOrFail($internship);

        $permissions = EffectivePermissions::resolveForInternship($request->user(), $workspace, $internshipModel);
        abort_unless($permissions['view_internship'], 403);

        $reportModel = $internshipModel->reports()->where('id', $report)->firstOrFail();

        abort_if($reportModel->status !== 'generated' || $reportModel->file_path === null, 404);

        $html = Storage::disk('local')->get($reportModel->file_path);

        if ($format === 'pdf') {
            // isRemoteEnabled = false : aucune ressource externe (SECURITY).
            $dompdf = new Dompdf(['isRemoteEnabled' => false]);
            $dompdf->setPaper('A4');
            $dompdf->loadHtml($html);
            $dompdf->render();
            $content = (string) $dompdf->output();
            $extension = 'pdf';
            $mime = 'application/pdf';
        } else {
            $tempPath = tempnam(sys_get_temp_dir(), 'stage-tracker-');
            file_put_contents($tempPath, $this->xhtmlFragment($html));
            $document = (new Html)->load($tempPath);
            unlink($tempPath);

            $writer = IOFactory::createWriter($document, 'Word2007');
            $tempPath = tempnam(sys_get_temp_dir(), 'stage-tracker-');
            $writer->save($tempPath);
            $content = (string) file_get_contents($tempPath);
            unlink($tempPath);
            $extension = 'docx';
            $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        }

        $exportPath = 'reports/exports/'.$reportModel->id.'.'.$extension;
        Storage::disk('local')->put($exportPath, $content);

        $this->audit->record(
            'report.exported',
            $workspace,
            $reportModel,
            ['status' => $format],
            $request->user(),
        );

        $name = str_replace([' ', '/'], '-', $this->filename($reportModel));

        return Storage::disk('local')->download(
            $exportPath,
            str_replace('.html', '.'.$extension, $name),
            ['Content-Type' => $mime],
        );
    }

    private function filename(Report $report): string
    {
        return str_replace([' ', '/'], '-', $report->internship->title)
            .'-'.$report->templateVersion->template->name
            .'-'.$report->templateVersion->version
            .'.html';
    }

    /**
     * Le lecteur HTML de PhpWord exige un fragment XHTML : corps du rapport
     * sans head/meta, balises vides auto-fermées.
     */
    private function xhtmlFragment(string $html): string
    {
        $fragment = preg_match('/<body>(.*)<\/body>/s', $html, $matches)
            ? $matches[1]
            : $html;

        $fragment = (string) preg_replace('/<(br|hr|img)([^>]*?)\/?>/i', '<$1$2/>', $fragment);

        // Le lecteur PhpWord charge un document complet : racine <html>
        // avec un unique <body> (loadXML → getElementsByTagName('body')).
        return '<html><body>'.$fragment.'</body></html>';
    }
}
