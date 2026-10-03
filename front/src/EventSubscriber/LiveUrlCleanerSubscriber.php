<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\UX\TwigComponent\MountedComponent;

/**
 * Les LiveProp `url: true` écrivent toutes leurs valeurs dans l'URL, même celles laissées par défaut
 * (`?q=&hp=0&attack=0&sort=id…`). On retire ces paramètres de l'URL renvoyée au navigateur, pour qu'une
 * recherche reste lisible et partageable : `/?types[0]=fire&speed=120`.
 *
 * S'exécute après le LiveUrlSubscriber de UX (priorité -10) et compare chaque paramètre à la valeur par défaut
 * de la propriété du composant.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -10)]
final class LiveUrlCleanerSubscriber
{
    /** En-tête posé par LiveUrlSubscriber (sa constante est privée). */
    private const string URL_HEADER = 'X-Live-Url';

    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $mounted = $event->getRequest()->attributes->get('_mounted_component');
        $url = $response->headers->get(self::URL_HEADER);

        if (!$event->isMainRequest() || !$mounted instanceof MountedComponent || null === $url || !str_contains($url, '?')) {
            return;
        }

        [$path, $queryString] = explode('?', $url, 2);
        parse_str($queryString, $query);
        $component = new \ReflectionObject($mounted->getComponent());

        foreach ($query as $name => $value) {
            if ($component->hasProperty($name) && $this->isDefault($component->getProperty($name), $value)) {
                unset($query[$name]);
            }
        }

        $response->headers->set(self::URL_HEADER, $path.($query ? '?'.http_build_query($query) : ''));
    }

    private function isDefault(\ReflectionProperty $property, mixed $value): bool
    {
        if (!$property->hasDefaultValue()) {
            return false;
        }

        $default = $property->getDefaultValue();

        return \is_array($default) ? [] === array_filter((array) $value, static fn ($v): bool => '' !== $v) : (string) $default === (string) $value;
    }
}
