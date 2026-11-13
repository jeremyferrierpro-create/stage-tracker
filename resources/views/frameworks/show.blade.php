@extends('layouts.app')

@section('title', $framework->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => 'Référentiels', 'url' => route('frameworks.index')],
        ['label' => $framework->name],
    ]"/>
@endsection

@section('content')
    <h1>{{ $framework->name }}</h1>
    <p>
        <span class="badge">{{ $framework->statusLabel() }}</span>
        @if ($framework->issuing_authority)
            <span class="muted">— {{ $framework->issuing_authority }}</span>
        @endif
    </p>
    @if ($framework->description)
        <p>{{ $framework->description }}</p>
    @endif

    @can('manage', $framework)
        <p>
            <a class="button secondary" href="{{ route('frameworks.edit', $framework) }}">Modifier le référentiel</a>
        </p>
    @endcan

    @can('manage', $framework)
        <div class="card">
            <h2>Nouvelle version</h2>
            <form method="POST" action="{{ route('frameworks.versions.store', $framework) }}" class="actions">
                @csrf
                <div>
                    <label for="version">Numéro de version</label>
                    <input id="version" type="text" name="version" required maxlength="40" placeholder="v1.0.0">
                    @error('version') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="effective_date">Date d'effet</label>
                    <input id="effective_date" type="date" name="effective_date">
                </div>
                <div style="align-self: end;">
                    <button type="submit">Créer la version</button>
                </div>
            </form>
        </div>
    @endcan

    @forelse ($framework->versions as $version)
        <div class="card">
            <h2>
                Version {{ $version->version }}
                <span class="badge">{{ $version->statusLabel() }}</span>
            </h2>
            <p class="muted">
                @if ($version->effective_date)
                    Date d'effet : {{ $version->effective_date->format('d/m/Y') }}.
                @endif
                @if ($version->published_at)
                    Publiée le {{ $version->published_at->format('d/m/Y H:i') }}.
                @endif
            </p>

            @if ($version->isPublished())
                <p class="muted">Version publiée : contenu immuable (INV-08).</p>
            @endif

            @can('manage', $framework)
                @if (! $version->isPublished())
                    <form method="POST" action="{{ route('frameworks.versions.publish', [$framework, $version]) }}" class="inline">
                        @csrf
                        <button type="submit" class="small">Publier cette version</button>
                    </form>
                @endif
            @endcan

            {{-- Blocs --}}
            <h3>Blocs</h3>
            @if ($version->blocks->isEmpty())
                <p class="muted">Aucun bloc.</p>
            @else
                @foreach ($version->blocks as $block)
                    <div class="card">
                        <strong>{{ $block->code }}</strong> — {{ $block->title }}
                        @if ($block->description)
                            <p class="muted">{{ $block->description }}</p>
                        @endif

                        @can('manage', $framework)
                            @if (! $version->isPublished())
                                <form method="POST" action="{{ route('frameworks.blocks.destroy', [$version, $block]) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="danger small">Supprimer le bloc</button>
                                </form>
                            @endif
                        @endcan

                        {{-- Compétences du bloc --}}
                        @php($blockCompetencies = $version->competencies->where('block_id', $block->id))
                        @if ($blockCompetencies->isNotEmpty())
                            <ul>
                                @foreach ($blockCompetencies as $competency)
                                    @include('frameworks.partials.competency', ['version' => $version, 'competency' => $competency, 'framework' => $framework])
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            @endif

            {{-- Compétences sans bloc --}}
            @php($orphanCompetencies = $version->competencies->whereNull('block_id'))
            @if ($orphanCompetencies->isNotEmpty())
                <h4>Compétences sans bloc</h4>
                <ul>
                    @foreach ($orphanCompetencies as $competency)
                        @include('frameworks.partials.competency', ['version' => $version, 'competency' => $competency, 'framework' => $framework])
                    @endforeach
                </ul>
            @endif

            @can('manage', $framework)
                @if (! $version->isPublished())
                    <div class="card">
                        <h4>Ajouter un bloc</h4>
                        <form method="POST" action="{{ route('frameworks.blocks.store', $version) }}">
                            @csrf
                            <div class="actions">
                                <div>
                                    <label for="block_code_{{ $version->id }}">Code</label>
                                    <input id="block_code_{{ $version->id }}" type="text" name="code" required maxlength="40">
                                </div>
                                <div>
                                    <label for="block_title_{{ $version->id }}">Titre</label>
                                    <input id="block_title_{{ $version->id }}" type="text" name="title" required maxlength="200">
                                </div>
                                <div>
                                    <label for="block_position_{{ $version->id }}">Position</label>
                                    <input id="block_position_{{ $version->id }}" type="number" name="position" min="0" max="9999" value="0">
                                </div>
                                <div style="align-self: end;">
                                    <button type="submit" class="small">Ajouter le bloc</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="card">
                        <h4>Ajouter une compétence</h4>
                        <form method="POST" action="{{ route('frameworks.competencies.store', $version) }}">
                            @csrf
                            <div class="actions">
                                <div>
                                    <label>Code</label>
                                    <input type="text" name="code" required maxlength="40">
                                </div>
                                <div>
                                    <label>Titre</label>
                                    <input type="text" name="title" required maxlength="200">
                                </div>
                                <div>
                                    <label>Bloc</label>
                                    <select name="block_id">
                                        <option value="">— sans bloc —</option>
                                        @foreach ($version->blocks as $block)
                                            <option value="{{ $block->id }}">{{ $block->code }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label>Compétence parente</label>
                                    <select name="parent_id">
                                        <option value="">— aucune —</option>
                                        @foreach ($version->competencies as $parent)
                                            <option value="{{ $parent->id }}">{{ $parent->code }} — {{ $parent->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label>Position</label>
                                    <input type="number" name="position" min="0" max="9999" value="0">
                                </div>
                                <div style="align-self: end;">
                                    <button type="submit" class="small">Ajouter la compétence</button>
                                </div>
                            </div>
                        </form>
                    </div>
                @endif
            @endcan
        </div>
    @empty
        <div class="card">
            <p class="muted">Aucune version pour ce référentiel.</p>
        </div>
    @endforelse
@endsection