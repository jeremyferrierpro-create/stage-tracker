@php($competency = $competency)
<li style="margin-bottom: .75rem;">
    <strong>{{ $competency->code }}</strong> — {{ $competency->title }}
    @if ($competency->block_id)
        <span class="muted">(bloc {{ $competency->block?->code }})</span>
    @endif
    @if ($competency->parent_id)
        <span class="muted">(parent {{ $competency->parent?->code }})</span>
    @endif
    @if ($competency->description)
        <p class="muted" style="margin: .2rem 0;">{{ $competency->description }}</p>
    @endif

    @if ($competency->criteria->isNotEmpty())
        <ul>
            @foreach ($competency->criteria as $criterion)
                <li>
                    {{ $criterion->label }}
                    @if ($criterion->description)
                        <span class="muted">— {{ $criterion->description }}</span>
                    @endif
                    @can('manage', $framework)
                        @if (! $version->isPublished())
                            <form method="POST" action="{{ route('frameworks.criteria.destroy', [$competency, $criterion]) }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="danger small">Supprimer le critère</button>
                            </form>
                        @endif
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif

    @can('manage', $framework)
        @if (! $version->isPublished())
            <form method="POST" action="{{ route('frameworks.criteria.store', $competency) }}" class="actions">
                @csrf
                <div>
                    <label>Critère</label>
                    <input type="text" name="label" required maxlength="200" placeholder="Libellé du critère">
                </div>
                <div>
                    <label>Description</label>
                    <input type="text" name="description" maxlength="2000">
                </div>
                <div style="align-self: end;">
                    <button type="submit" class="small">Ajouter le critère</button>
                </div>
            </form>

            <form method="POST" action="{{ route('frameworks.competencies.destroy', [$version, $competency]) }}" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="danger small">Supprimer la compétence</button>
            </form>
        @endif
    @endcan
</li>
