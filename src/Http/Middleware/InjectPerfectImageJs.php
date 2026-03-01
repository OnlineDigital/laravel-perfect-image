<?php

namespace OnlineDigital\PerfectImage\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectPerfectImageJs
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Only inject for HTML responses
        if (!$response->isSuccessful() || !$this->isHtml($response)) {
            return $response;
        }
        
        $js = \OnlineDigital\PerfectImage\ImageManager::getJavaScript();
        $script = '<script>' . $js . '</script>';
        
        // Inject before </body>
        $content = $response->getContent();
        $content = str_replace('</body>', $script . '</body>', $content);
        
        $response->setContent($content);
        
        return $response;
    }
    
    protected function isHtml(Response $response): bool
    {
        $headers = $response->headers;
        
        if ($headers->has('Content-Type')) {
            return str_contains($headers->get('Content-Type'), 'text/html');
        }
        
        return true;
    }
}
