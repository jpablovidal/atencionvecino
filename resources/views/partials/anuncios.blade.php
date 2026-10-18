@php
  try {
    $anuncios = \App\Models\Anuncio::query()->vigentes($vecino || ! auth()->check() ? 'vecinos' : 'funcionarios')->orderBy('orden')->orderBy('id')->limit(3)->get();
  } catch (\Throwable) { $anuncios = collect(); }
@endphp
@if($anuncios->isNotEmpty())
<div id="sgd-anuncios" hidden>
@foreach($anuncios as $an)
 <section class="sgd-anuncio" data-anuncio="{{ $an->id }}" data-version="{{ $an->updated_at?->timestamp }}" aria-labelledby="an-t{{ $an->id }}">
  <h2 id="an-t{{ $an->id }}">{{ $an->titulo }}</h2>
  @if($an->medio_tipo === 'imagen')<img src="{{ route('anuncios.medio', $an) }}?v={{ substr((string) $an->medio_sha256, 0, 8) }}" alt="{{ $an->medio_alt }}" class="sgd-anuncio-medio">
  @elseif($an->medio_tipo === 'video')<video src="{{ route('anuncios.medio', $an) }}?v={{ substr((string) $an->medio_sha256, 0, 8) }}" class="sgd-anuncio-medio" controls preload="none" playsinline aria-label="{{ $an->medio_alt }}">Su navegador no reproduce este video.</video>@endif
  @if($an->descripcion)<p class="sgd-anuncio-texto">{!! nl2br(e($an->descripcion)) !!}</p>@endif
  @if($an->enlace_url)<p><a class="btn btn-primary" href="{{ str_starts_with($an->enlace_url, '/') ? url($an->enlace_url) : $an->enlace_url }}" @unless(str_starts_with($an->enlace_url, '/')) rel="noopener noreferrer" target="_blank" @endunless>{{ $an->enlace_texto }}</a></p>@endif
 </section>
@endforeach
</div>
<div class="modal fade" id="sgd-anuncio-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="sgd-anuncio-titulo" hidden>
 <div class="modal-dialog modal-dialog-centered modal-lg" role="document"><div class="modal-content">
  <div class="modal-header"><p class="h5 modal-title mb-0" id="sgd-anuncio-titulo">Anuncio</p><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar anuncio"><span aria-hidden="true">&times;</span></button></div>
  <div class="modal-body" id="sgd-anuncio-cuerpo"></div>
  <div class="modal-footer justify-content-between"><div class="form-check"><input class="form-check-input" type="checkbox" id="sgd-anuncio-no"><label class="form-check-label" for="sgd-anuncio-no">No volver a mostrar este anuncio</label></div><button type="button" class="btn btn-outline-primary" data-dismiss="modal">Cerrar</button></div>
 </div></div>
</div>
@endif
