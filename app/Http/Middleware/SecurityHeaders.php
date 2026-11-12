<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos HTTP que instruem o navegador a recusar ataques comuns.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Não permite que a intranet seja exibida dentro de outro site (clickjacking)
        $response->headers->set('X-Frame-Options', 'DENY');

        // Não deixa o navegador "adivinhar" o tipo de um arquivo
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Endereços internos não vazam para sites externos ao clicar em links
        $response->headers->set('Referrer-Policy', 'same-origin');

        // A intranet não usa câmera, microfone nem localização
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // Formulários só enviam dados para a própria intranet
        $response->headers->set(
            'Content-Security-Policy',
            "frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'"
        );

        // Não anuncia a versão do PHP
        $response->headers->remove('X-Powered-By');
        header_remove('X-Powered-By');

        // Em produção, com HTTPS: o navegador passa a recusar a versão sem criptografia
        if ($request->secure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}