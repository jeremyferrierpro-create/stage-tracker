<?php

namespace App\Domain\Identity;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\InternshipParticipant;
use App\Domain\Notifications\NotificationService;
use App\Mail\WorkspaceInvitationMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cycle de vie d'une invitation : création, acceptation, refus/annulation
 * (T-007, APPFLOW §6.1).
 */
final class InvitationService
{
    public const EXPIRES_IN_DAYS = 7;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Rôles que l'inviteur peut proposer, selon son rôle dans le workspace.
     *
     * @return list<string>
     */
    public function assignableRolesFor(Workspace $workspace, User $inviter): array
    {
        return match ($workspace->roleFor($inviter)) {
            'owner' => Invitation::OWNER_ASSIGNABLE_ROLES,
            'admin' => Invitation::ADMIN_ASSIGNABLE_ROLES,
            default => [],
        };
    }

    /**
     * Crée (ou renouvelle) une invitation et envoie le lien par e-mail.
     *
     * @throws ValidationException
     */
    public function invite(Workspace $workspace, User $inviter, string $email, string $role): Invitation
    {
        $email = Str::lower(trim($email));
        $assignableRoles = $this->assignableRolesFor($workspace, $inviter);

        if ($assignableRoles === []) {
            throw ValidationException::withMessages([
                'email' => 'Vous n\'êtes pas autorisé à inviter des participants.',
            ]);
        }

        if (! in_array($role, $assignableRoles, true)) {
            throw ValidationException::withMessages([
                'role' => 'Ce rôle n\'est pas autorisé pour votre niveau de permission.',
            ]);
        }

        $existingUser = User::where('email', $email)->first();

        if ($existingUser !== null && $workspace->members()->where('user_id', $existingUser->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Cette personne est déjà membre de l\'espace.',
            ]);
        }

        $pending = $workspace->invitations()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->first();

        if ($pending !== null && ! $pending->isExpired()) {
            throw ValidationException::withMessages([
                'email' => 'Une invitation est déjà en attente pour cette adresse.',
            ]);
        }

        $token = Str::random(48);

        $attributes = [
            'email' => $email,
            'role' => $role,
            'token_hash' => Invitation::hashToken($token),
            'expires_at' => now()->addDays(self::EXPIRES_IN_DAYS),
            'accepted_at' => null,
            'created_by' => $inviter->id,
        ];

        $invitation = DB::transaction(function () use ($workspace, $pending, $attributes, $inviter): Invitation {
            if ($pending !== null) {
                $pending->forceFill($attributes)->save();
                $invitation = $pending;
            } else {
                $invitation = $workspace->invitations()->create($attributes);
            }

            $this->audit->record(
                'invitation.created',
                $workspace,
                $invitation,
                [
                    'role' => $invitation->role,
                    'email_hash' => hash('sha256', $invitation->email),
                ],
                $inviter,
            );

            return $invitation;
        });

        Mail::to($email)->send(new WorkspaceInvitationMail(
            $invitation,
            $token,
            $workspace->name,
            $inviter->name,
            Workspace::roleLabel($invitation->role),
        ));

        $invitation->plain_token = $token;

        return $invitation;
    }

    /**
     * Accepte l'invitation et crée l'appartenance avec exactement le rôle
     * proposé (aucune élévation de privilège).
     *
     * @throws ValidationException
     */
    public function accept(Invitation $invitation, User $user): WorkspaceMember
    {
        $workspace = $invitation->workspace;

        if ($workspace === null) {
            throw ValidationException::withMessages([
                'invitation' => 'Cette invitation n\'est plus valide.',
            ]);
        }

        if ($invitation->isAccepted()) {
            throw ValidationException::withMessages([
                'invitation' => 'Cette invitation a déjà été utilisée.',
            ]);
        }

        if ($invitation->isExpired()) {
            throw ValidationException::withMessages([
                'invitation' => 'Cette invitation a expiré.',
            ]);
        }

        if (! in_array($invitation->role, Invitation::OWNER_ASSIGNABLE_ROLES, true)) {
            throw ValidationException::withMessages([
                'invitation' => 'Le rôle proposé n\'est pas valide.',
            ]);
        }

        if (Str::lower($invitation->email) !== Str::lower((string) $user->email)) {
            throw ValidationException::withMessages([
                'invitation' => 'Cette invitation ne correspond pas à votre adresse e-mail.',
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'invitation' => 'Votre compte est désactivé.',
            ]);
        }

        if (! $workspace->isActive()) {
            throw ValidationException::withMessages([
                'invitation' => 'Cet espace de travail n\'accepte plus d\'invitations.',
            ]);
        }

        if ($workspace->members()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'invitation' => 'Vous êtes déjà rattaché à cet espace de travail.',
            ]);
        }

        return DB::transaction(function () use ($invitation, $workspace, $user): WorkspaceMember {
            $member = WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => $invitation->role,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $invitation->forceFill(['accepted_at' => now()])->save();

            // Rattachement automatique aux stages du workspace. Les rôles à
            // portée « stage attribué » (supervisor, tutor, viewer) sont
            // attachés en lecture/écriture selon la matrice : viewer devient
            // `observer` (lecture seule) pour conserver la portée restreinte.
            $stageRoles = [
                'supervisor' => 'supervisor',
                'tutor' => 'tutor',
                'viewer' => 'observer',
            ];

            if (isset($stageRoles[$invitation->role])) {
                $internships = $workspace->internships()->get();
                foreach ($internships as $internship) {
                    InternshipParticipant::firstOrCreate([
                        'internship_id' => $internship->id,
                        'workspace_id' => $workspace->id,
                        'user_id' => $user->id,
                    ], [
                        'role' => $stageRoles[$invitation->role],
                        'status' => 'active',
                    ]);
                }
            }

            $this->audit->record(
                'invitation.accepted',
                $workspace,
                $invitation,
                [
                    'role' => $invitation->role,
                    'email_hash' => hash('sha256', $invitation->email),
                ],
                $user,
            );

            // Notifie les gestionnaires de l'espace (REQ-F-002).
            $managers = User::query()
                ->whereIn('id', $workspace->members()
                    ->where('status', 'active')
                    ->whereIn('role', ['owner', 'admin'])
                    ->pluck('user_id'))
                ->get();

            foreach ($managers as $manager) {
                $this->notifications->send(
                    $workspace,
                    $manager,
                    'member_joined',
                    $user->name.' a rejoint l\'espace',
                    Workspace::roleLabel($invitation->role),
                    [
                        'url' => route('workspace.members.index', $workspace),
                        'dedupe_key' => 'member_joined:'.$user->id.':'.$workspace->id,
                    ],
                );
            }

            return $member;
        });
    }

    /**
     * Refus par l'invité : l'invitation est supprimée, l'événement reste dans
     * le journal d'audit.
     */
    public function decline(Invitation $invitation, ?User $actor = null): void
    {
        $this->remove($invitation, $actor, 'declined');
    }

    /**
     * Annulation par un administrateur.
     */
    public function cancel(Invitation $invitation, User $actor): void
    {
        $this->remove($invitation, $actor, 'cancelled');
    }

    private function remove(Invitation $invitation, ?User $actor, string $reason): void
    {
        DB::transaction(function () use ($invitation, $actor, $reason): void {
            $this->audit->record(
                'invitation.'.$reason,
                $invitation->workspace,
                $invitation,
                [
                    'role' => $invitation->role,
                    'email_hash' => hash('sha256', $invitation->email),
                    'reason' => $reason,
                ],
                $actor,
            );

            $invitation->delete();
        });
    }
}
