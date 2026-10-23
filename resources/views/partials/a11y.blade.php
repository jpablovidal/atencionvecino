<div class="a11y-quick" role="group" aria-label="Accesibilidad">
  <button type="button" class="a11y-icon" data-a11y-toggle="contrast" data-nombre="Alto contraste" aria-pressed="false" title="Alto contraste"><svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 18 18"><circle cx="9" cy="9" r="7.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M9 1.5a7.5 7.5 0 0 1 0 15z" fill="currentColor"/></svg><span class="sr-only">Alto contraste</span></button>
  <button type="button" class="a11y-icon" data-a11y-size="down" title="Reducir texto"><span aria-hidden="true">A−</span><span class="sr-only">Reducir texto</span></button>
  <button type="button" class="a11y-icon" data-a11y-size="up" title="Aumentar texto"><span aria-hidden="true">A+</span><span class="sr-only">Aumentar texto</span></button>
  <details class="a11y-mas"><summary title="Más opciones de accesibilidad">Accesibilidad</summary>
    <div class="a11y-bar" role="region" aria-label="Opciones de accesibilidad"><div class="a11y-panel">
      <span class="grupo" role="group" aria-label="Tamaño del texto">Texto:
        <button type="button" data-a11y-size="reset">Texto normal</button>
        <span id="a11y-tamano">100%</span></span>
      <span class="grupo" role="group" aria-label="Visualización">
        <button type="button" data-a11y-toggle="legible" data-nombre="Fuente y espaciado legibles" aria-pressed="false">Texto legible</button>
        <button type="button" data-a11y-toggle="links" data-nombre="Enlaces subrayados" aria-pressed="false">Subrayar enlaces</button>
        <button type="button" data-a11y-toggle="motion" data-nombre="Sin animaciones" aria-pressed="false">Sin animaciones</button>
        <button type="button" id="a11y-reset">Restablecer</button></span>
      <span class="grupo"><a href="{{ route('accesibilidad') }}">Declaración de accesibilidad</a><a href="{{ \App\Support\ManualRenderer::ayudaUrl() }}">{{ \App\Support\ManualRenderer::ayudaEtiqueta() }}</a></span>
    </div></div>
  </details>
</div>
