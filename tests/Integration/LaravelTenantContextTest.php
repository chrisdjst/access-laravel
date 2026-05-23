<?php

declare(strict_types=1);

use ModularizeRbac\Core\Application\Ports\TenantContext;

it('returns null when the container has no tenant binding', function (): void {
    /** @var TenantContext $context */
    $context = $this->app->make(TenantContext::class);

    expect($context->currentTenantId())->toBeNull();
});

it('returns the bound UUID', function (): void {
    $this->app->instance('access.current_tenant_id', '11111111-1111-1111-1111-111111111111');

    /** @var TenantContext $context */
    $context = $this->app->make(TenantContext::class);
    $this->app->forgetInstance(TenantContext::class);
    $context = $this->app->make(TenantContext::class);

    expect($context->currentTenantId()?->value)->toBe('11111111-1111-1111-1111-111111111111');
});

it('returns null when the binding is an empty string', function (): void {
    $this->app->instance('access.current_tenant_id', '');

    /** @var TenantContext $context */
    $this->app->forgetInstance(TenantContext::class);
    $context = $this->app->make(TenantContext::class);

    expect($context->currentTenantId())->toBeNull();
});

it('returns null when the binding holds a non-UUID value', function (): void {
    $this->app->instance('access.current_tenant_id', 'not-a-uuid');

    $this->app->forgetInstance(TenantContext::class);
    /** @var TenantContext $context */
    $context = $this->app->make(TenantContext::class);

    expect($context->currentTenantId())->toBeNull();
});
