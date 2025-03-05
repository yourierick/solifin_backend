use Illuminate\Routing\Exceptions\InvalidSignatureException;

public function register()
{
    $this->renderable(function (InvalidSignatureException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Le lien de vérification est invalide ou a expiré',
            'redirect' => config('frontend.url') . '/verification-error'
        ], 403);
    });
}

public function render($request, Throwable $exception)
{
    if ($request->exceptsJson() && $exception instanceof AuthenticationException) {
        return redirect(env('FRONTEND_URL', 'http://localhost:5173'). '/login');
    }

    return parent::render($request, $exception);
}