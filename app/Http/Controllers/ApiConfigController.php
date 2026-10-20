<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Services\Admin\ApiCatalogo;
use App\Services\Admin\ApiConfigService;
use App\Services\AuditoriaService;
use App\Services\Firma\FirmaClient;
use App\Services\Firma\FirmaException;
use App\Services\Repositorio\CredencialDriveService;
use App\Support\Run;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ApiConfigController extends Controller
{
    public function __construct(private readonly ApiConfigService $svc, private readonly AuditoriaService $audit, private readonly CredencialDriveService $cred) {}

    private function servicio(string $s): array
    {
        $c = ApiCatalogo::servicios();
        abort_unless(isset($c[$s]), 404);

        return $c[$s];
    }

    public function index()
    {
        $filas = [];
        foreach (ApiCatalogo::servicios() as $k => $d) {
            $e = $this->svc->estado($k);
            $ef = $this->svc->efectivo($k);
            $filas[] = ['clave' => $k, 'etiqueta' => $d['etiqueta'], 'descripcion' => $d['descripcion'], 'origen' => $e['activo'] ? 'BD (mantenedor)' : '.env', 'modo' => config(array_values(array_filter($d['campos'], fn ($c) => $c[1] === 'select'))[0][2] ?? 'sgd.x') ?? '—',
                'faltan' => collect($ef)->filter(fn ($x) => ! $x['definido'])->count(), 'total' => count($ef)];
        }

        return view('app.admin.api', ['filas' => $filas, 'secretosBd' => ApiConfigService::secretosEnBd()]);
    }

    public function editar(string $servicio)
    {
        $d = $this->servicio($servicio);

        return view('app.admin.api_form', ['clave' => $servicio, 'd' => $d, 'estado' => $this->svc->estado($servicio), 'ef' => $this->svc->efectivo($servicio), 'secretosBd' => ApiConfigService::secretosEnBd(), 'pruebas' => session('pruebas')]);
    }

    public function actualizar(Request $r, string $servicio)
    {
        $d = $this->servicio($servicio);
        $datos = $r->except(['_token', 'borrar', 'activo']);
        $subida = false;
        try {
            foreach ($d['campos'] as $k => $def) {
                $op = $def[3] ?? [];
                if (! empty($op['id_drive']) && isset($datos[$k])) {
                    $datos[$k] = CredencialDriveService::extraerId((string) $datos[$k]); // se acepta pegar la URL de Drive
                }
                if (! empty($op['subida']) && $r->hasFile('archivo_'.$k)) {
                    $r->validate(['archivo_'.$k => 'file|max:20']);
                    $c = $this->cred->guardar($r->file('archivo_'.$k), (string) $op['subida']);
                    $datos[$k] = $c['ruta'];
                    $subida = true;
                    $this->audit->registrar('api.credencial_subida', 'api_config', $servicio, ['campo' => $k, 'cuenta' => $c['email'], 'proyecto' => $c['proyecto']]);
                }
                unset($datos['archivo_'.$k]);
            }
            $this->svc->guardar($servicio, $datos, $r->boolean('activo'), (array) $r->input('borrar', []), $r->user());
        } catch (FlujoException $e) {
            return back()->withInput($r->except(['_token']) + [])->with('error', $e->getMessage());
        }
        $msg = 'Configuración guardada.'.($subida ? ' La clave JSON se cargó y guardó en el servidor.' : '').($subida && ! $r->boolean('activo') ? ' Marque «Usar esta configuración de la base de datos» y guarde de nuevo para que se aplique.' : '');

        return redirect()->route('app.admin.api.editar', $servicio)->with('ok', $msg);
    }

    public function volverEnv(Request $r, string $servicio)
    {
        $this->servicio($servicio);
        $this->svc->volverAEnv($servicio, $r->user());

        return back()->with('ok', 'Se usará la configuración del archivo .env.');
    }

    public function probar(string $servicio)
    {
        $this->servicio($servicio);

        return back()->with('pruebas', $this->svc->probar($servicio));
    }

    /** Envía un correo de prueba con el método de envío vigente y muestra el resultado (sin detalles internos ni secretos). */
    public function correoPrueba(Request $r)
    {
        $d = $r->validate(['destino' => 'required|email:rfc|max:190']);
        $metodo = (string) config('mail.default');
        try {
            Mail::raw('Correo de prueba del Sistema de Gestión Documental de la I. Municipalidad de Santo Domingo. Si lo recibe, el envío funciona.', fn ($m) => $m->to($d['destino'])->subject('Prueba de correo — SGD Santo Domingo'));
            $ok = true;
            $det = $metodo === 'log' ? 'El método es «solo registro»: el mensaje quedó en el registro del servidor y no salió ningún correo.' : 'Enviado a '.$d['destino'].' con el método «'.$metodo.'». Revise la bandeja (y el spam).';
        } catch (\Throwable $e) {
            report($e);
            $ok = false;
            $det = 'No se pudo enviar (método «'.$metodo.'»). Revise la cuenta de servicio, la delegación de dominio y el remitente.';
        }
        $this->audit->registrar('correo.prueba', null, null, ['metodo' => $metodo, 'ok' => $ok]);

        return back()->with('pruebas', [['Correo de prueba', $ok, $det]]);
    }

    /**
     * «Probar la firma»: firma un PDF de ejemplo con la configuración guardada y el OTP del titular, para confirmar que FirmaGob responde.
     * El resultado no se guarda. Con el ambiente de producción se usa el certificado real, por eso exige confirmación expresa.
     */
    public function firmaPrueba(Request $r)
    {
        $d = $r->validate(['entiendo' => 'accepted', 'run' => 'required|string|max:14', 'otp' => ['required', 'regex:/^\d{6}$/']], ['entiendo.accepted' => 'Confirme que entiende que se firmará un documento de prueba.', 'otp.regex' => 'El OTP son 6 dígitos.']);
        $run = Run::normalizar($d['run']);
        if (! $run) {
            return back()->with('pruebas', [['Firma de prueba', false, 'El RUN no es válido.']]);
        }
        $modo = (string) config('sgd.firmagob.mode');
        try {
            $dompdf = new Dompdf;
            $dompdf->loadHtml('<p>Documento de prueba de firma — SGD Municipalidad de Santo Domingo — '.now('America/Santiago')->format('d-m-Y H:i').'</p>');
            $dompdf->render();
            $res = app(FirmaClient::class)->firmar($dompdf->output(), $run, $d['otp'], 'Prueba de firma');
            $ok = true;
            $det = ! empty($res['simulada']) ? 'Modo simulado: no se llamó a FirmaGob (no hay firma real).' : 'FirmaGob firmó el documento de prueba (ambiente «'.$modo.'») y la respuesta es válida. El documento no se guardó.';
        } catch (FirmaException $e) {
            $ok = false;
            $det = $e->getMessage();
        } catch (\Throwable $e) {
            report($e);
            $ok = false;
            $det = 'No se pudo completar la prueba de firma.';
        }
        $this->audit->registrar('firma.prueba', null, null, ['modo' => $modo, 'run' => Run::enmascarar($run), 'ok' => $ok]);

        return back()->with('pruebas', [['Firma de prueba (ambiente '.$modo.')', $ok, $det]]);
    }
}
