// Archivo externo (no inline) para respetar la Content-Security-Policy.
window.addEventListener('load', function () {
  window.ui = SwaggerUIBundle({
    url: './openapi.yaml',
    dom_id: '#swagger-ui',
    deepLinking: true,
    persistAuthorization: false, // el token no se guarda en localStorage
    displayRequestDuration: true,
    tryItOutEnabled: true,
    filter: true,
    docExpansion: 'list',
    presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
    layout: 'StandaloneLayout',
  });
});
