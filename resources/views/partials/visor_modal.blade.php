{{-- Visor de archivos del expediente: se abre en un modal y puede desplegarse en una pestaña nueva. Sin scripts en línea. --}}
<div class="modal fade" id="sgd-visor" tabindex="-1" role="dialog" aria-labelledby="sgd-visor-titulo" aria-hidden="true" hidden>
 <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document"><div class="modal-content">
  <div class="modal-header"><h2 class="modal-title h5" id="sgd-visor-titulo">Archivo</h2>
   <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
  <div class="modal-body p-0" id="sgd-visor-cuerpo" style="min-height:65vh"></div>
  <div class="modal-footer"><a class="btn btn-outline-primary" id="sgd-visor-nueva" href="#" target="_blank" rel="noopener noreferrer">Abrir en una pestaña nueva<span class="sr-only"> (se abre en otra pestaña)</span></a>
   <a class="btn btn-outline-secondary" id="sgd-visor-descarga" href="#">Descargar</a>
   <button type="button" class="btn btn-primary" data-dismiss="modal">Cerrar</button></div>
 </div></div>
</div>
@push('scripts')<script src="{{ asset('assets/js/visor.js') }}?v={{ substr((string) @md5_file(public_path('assets/js/visor.js')), 0, 8) }}" defer></script>@endpush
