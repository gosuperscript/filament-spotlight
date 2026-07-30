<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Support;

use Filament\Pages\Page;
use Filament\Panel;
use Filament\Resources\Pages\Page as ResourcePage;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Superscript\FilamentSpotlight\PageContext;
use Throwable;

class PageContextResolver
{
    /**
     * Resolve a client-reported URL to the panel page (and record) it points
     * at. The URL is untrusted input: it is matched through the router, the
     * page must belong to the given panel, and records resolve through the
     * resource's scoped Eloquent query.
     */
    public function resolve(?string $url, Panel $panel): ?PageContext
    {
        if (blank($url)) {
            return null;
        }

        try {
            $route = app(Router::class)->getRoutes()->match(Request::create($url));
        } catch (Throwable) {
            return null;
        }

        $page = $route->getAction('controller');

        if (! is_string($page) || ! class_exists($page)) {
            return null;
        }

        $resource = null;

        if (is_a($page, ResourcePage::class, true)) {
            $resource = $page::getResource();

            if (! in_array($resource, $panel->getResources(), true)) {
                return null;
            }
        } elseif (is_a($page, Page::class, true)) {
            if (! in_array($page, $panel->getPages(), true)) {
                return null;
            }
        } else {
            return null;
        }

        $record = null;
        $key = $route->parameter('record');

        if ($resource !== null && is_string($key)) {
            $record = $resource::resolveRecordRouteBinding($key);
        }

        return new PageContext($page, $resource, $record);
    }
}
