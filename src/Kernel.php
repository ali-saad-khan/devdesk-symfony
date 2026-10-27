<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    // Vercel functions have a read-only filesystem except /tmp.
    public function getCacheDir(): string
    {
        return $this->onVercel() ? '/tmp/devdesk/cache/'.$this->environment : parent::getCacheDir();
    }

    public function getLogDir(): string
    {
        return $this->onVercel() ? '/tmp/devdesk/log' : parent::getLogDir();
    }

    private function onVercel(): bool
    {
        return false !== getenv('VERCEL') && '' !== getenv('VERCEL');
    }
}
