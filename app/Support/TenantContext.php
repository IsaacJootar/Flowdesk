<?php

namespace App\Support;

class TenantContext
{
    private ?int $companyId = null;

    public function setCompanyId(?int $companyId): void
    {
        $this->companyId = $companyId !== null ? max(1, $companyId) : null;
    }

    public function clear(): void
    {
        $this->companyId = null;
    }

    public function companyId(): ?int
    {
        return $this->companyId;
    }

    /**
     * Run work inside an explicit tenant scope so console and queue flows can
     * safely reuse company-scoped models outside normal auth middleware.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runForCompany(int $companyId, callable $callback): mixed
    {
        $previousCompanyId = $this->companyId();

        $this->setCompanyId($companyId);

        try {
            return $callback();
        } finally {
            $this->setCompanyId($previousCompanyId);
        }
    }
}
