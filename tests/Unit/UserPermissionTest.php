<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

final class UserPermissionTest extends TestCase
{
    public function test_cashier_has_operational_permission_but_not_manager_approval(): void
    {
        $user = new User(['role' => 'KASIR']);

        self::assertTrue($user->hasPermission('inbound.create'));
        self::assertFalse($user->hasPermission('correction.approve'));
    }

    public function test_manager_has_approval_and_payment_permissions(): void
    {
        $user = new User(['role' => 'MANAGER_OWNER']);

        self::assertTrue($user->hasPermission('correction.approve'));
        self::assertTrue($user->hasPermission('payout.mark_paid'));
    }
}
