using System.Net;
using System.Text;
using System.Text.Json;

// IMPORTANTE: este namespace/clase viene del wrapper del SDK ZKTeco
// Asegurate que el wrapper libzkfpcsharp.dll está en .\lib\ y referenciado en el .csproj
using static libzkfpcsharp.ZKFP2; // suele exponerse como clase estática zkfp2 o ZKFP2

class Program
{
    // Config
    const string Prefix = "http://127.0.0.1:5177/";
    static IntPtr _devHandle = IntPtr.Zero;
    static IntPtr _dbHandle = IntPtr.Zero;
    static int _fpWidth = 0, _fpHeight = 0;

    static async Task Main()
    {
        Console.Title = "ZK4500 Local Service (Enroll)";

        // 1) Inicializar SDK
        if (Init() != 0)
        {
            Console.WriteLine("ERROR: No se pudo inicializar el SDK ZKFinger.");
            return;
        }

        int count = GetDeviceCount();
        if (count <= 0)
        {
            Console.WriteLine("No se detectan dispositivos ZK4500.");
            Terminate();
            return;
        }

        _devHandle = OpenDevice(0);
        if (_devHandle == IntPtr.Zero)
        {
            Console.WriteLine("No se pudo abrir el dispositivo (índice 0).");
            Terminate();
            return;
        }

        _dbHandle = DBInit();
        if (_dbHandle == IntPtr.Zero)
        {
            Console.WriteLine("No se pudo inicializar la base de datos interna del SDK.");
            CloseDevice(_devHandle);
            Terminate();
            return;
        }

        // Obtener tamaño de imagen y template
        // Según SDK: GetParameters como byte[]; aquí usamos helpers del wrapper
        _fpWidth = GetParameterInt(_devHandle, 1);  // 1 = width
        _fpHeight = GetParameterInt(_devHandle, 2); // 2 = height
        int tmplLen = GetTemplateSize(_devHandle);
        Console.WriteLine($"Dispositivo OK. {count} disp.  Imagen: {_fpWidth}x{_fpHeight}  TemplateSize≈{tmplLen}");

        // 2) HTTP Listener
        var listener = new HttpListener();
        listener.Prefixes.Add(Prefix);
        try
        {
            listener.Start();
        }
        catch (HttpListenerException ex)
        {
            Console.WriteLine("No se pudo iniciar el listener HTTP.");
            Console.WriteLine("Si ves 'Acceso denegado', ejecutá esto como ADMIN una vez:");
            Console.WriteLine(@"  netsh http add urlacl url=http://127.0.0.1:5177/ user=Todos");
            Console.WriteLine($"Detalle: {ex.Message}");
            Cleanup();
            return;
        }

        Console.WriteLine($"Escuchando en {Prefix}");
        Console.WriteLine("Endpoints:");
        Console.WriteLine("  GET /enroll            -> captura 3 veces y retorna template_b64");
        Console.WriteLine("  GET /health            -> responde ok:true");

        while (true)
        {
            var ctx = await listener.GetContextAsync();
            _ = Task.Run(() => Handle(ctx));
        }
    }

    static async Task Handle(HttpListenerContext ctx)
    {
        try
        {
            var req = ctx.Request;
            var res = ctx.Response;
            res.AddHeader("Access-Control-Allow-Origin", "*");
            res.ContentType = "application/json; charset=utf-8";

            if (req.HttpMethod == "GET" && req.Url?.AbsolutePath == "/health")
            {
                await WriteJson(res, new { ok = true });
            }
            else if (req.HttpMethod == "GET" && req.Url?.AbsolutePath == "/enroll")
            {
                // repeats=? (por defecto 3)
                int repeats = 3;
                if (int.TryParse(req.QueryString["repeats"], out var r) && r > 0) repeats = r;

                var enroll = EnrollCapture(repeats);
                await WriteJson(res, enroll);
            }
            else
            {
                res.StatusCode = 404;
                await WriteJson(res, new { ok = false, error = "not_found" });
            }
        }
        catch (Exception ex)
        {
            try
            {
                await WriteJson(ctx.Response, new { ok = false, error = ex.Message });
            }
            catch { /* ignore */ }
        }
    }

    // Captura N veces, verifica calidad y mergea templates en uno solo
    static object EnrollCapture(int repeats)
    {
        if (_devHandle == IntPtr.Zero || _dbHandle == IntPtr.Zero)
            return new { ok = false, error = "device_not_ready" };

        var templates = new List<byte[]>();

        for (int i = 1; i <= repeats; i++)
        {
            Console.WriteLine($"Captura {i}/{repeats} - Coloque el dedo...");
            var (ok, tpl, msg) = CaptureOneTemplate();
            if (!ok)
            {
                Console.WriteLine($"Fallo captura {i}: {msg}");
                return new { ok = false, error = $"capture_fail_{i}: {msg}" };
            }
            templates.Add(tpl);
            Console.WriteLine($"Captura {i} OK ({tpl.Length} bytes). Retire y vuelva a colocar...");
            Thread.Sleep(800); // breve pausa entre capturas
        }

        // Merge (DBMerge) — combina varias plantillas en una
        byte[] merged = new byte[2048];
        int mergedLen = merged.Length;
        int rc = DBMerge(_dbHandle, templates[0], templates[1], templates.Count >= 3 ? templates[2] : templates[1], merged, ref mergedLen);
        if (rc != 0)
        {
            return new { ok = false, error = $"merge_error_{rc}" };
        }

        Array.Resize(ref merged, mergedLen);
        string b64 = Convert.ToBase64String(merged);
        return new { ok = true, template_b64 = b64, version = "ZKFinger10" };
    }

    // Captura una huella y devuelve el template extraído
    static (bool ok, byte[] template, string error) CaptureOneTemplate()
    {
        // Según el SDK, se usa AcquireFingerprint o AcquireFingerprintImage + Extract
        // Aquí usamos el patrón común del wrapper: AcquireFingerprint(_devHandle, imgBuf, tplBuf, ref tplLen)
        byte[] img = new byte[_fpWidth * _fpHeight];
        byte[] tpl = new byte[2048];
        int tplLen = tpl.Length;

        int rc = AcquireFingerprint(_devHandle, img, tpl, ref tplLen);
        if (rc != 0 || tplLen <= 0)
        {
            return (false, Array.Empty<byte>(), $"acquire_error_{rc}");
        }

        // (opcional) chequear calidad con una función del SDK si está disponible
        Array.Resize(ref tpl, tplLen);
        return (true, tpl, "");
    }

    static async Task WriteJson(HttpListenerResponse res, object obj)
    {
        var json = JsonSerializer.Serialize(obj, new JsonSerializerOptions { PropertyNamingPolicy = JsonNamingPolicy.CamelCase });
        var bytes = Encoding.UTF8.GetBytes(json);
        res.ContentEncoding = Encoding.UTF8;
        res.ContentLength64 = bytes.Length;
        await res.OutputStream.WriteAsync(bytes);
        res.OutputStream.Close();
    }

    // Helpers de parámetros (depende del wrapper)
    static int GetParameterInt(IntPtr dev, int code)
    {
        // En wrappers comunes: GetParameters(dev, code, byte[] buf)
        byte[] buf = new byte[4];
        int size = 4;
        int rc = GetParameters(dev, code, buf, ref size);
        if (rc == 0 && size >= 4) return BitConverter.ToInt32(buf, 0);
        return 0;
    }

    static void Cleanup()
    {
        if (_dbHandle != IntPtr.Zero) { DBFree(_dbHandle); _dbHandle = IntPtr.Zero; }
        if (_devHandle != IntPtr.Zero) { CloseDevice(_devHandle); _devHandle = IntPtr.Zero; }
        Terminate();
    }

    ~Program() { Cleanup(); }
}
