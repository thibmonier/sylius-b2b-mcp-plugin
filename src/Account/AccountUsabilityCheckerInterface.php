<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Account;

use Sylius\Component\Core\Model\CustomerInterface;

/**
 * Extension point: may this customer connect an AI assistant and use the MCP endpoint? The plugin itself only
 * checks that the shop account is enabled; a checker adds a rule of the application (registration not approved,
 * account suspended, contract ended...).
 *
 * A class implementing it is tagged `tmonier_b2b_mcp.account_usability_checker` by autoconfiguration (use the
 * tag priority to order several checkers). It is asked at the OAuth consent and at every call of the endpoint, so
 * the answer must be cheap and must not depend on the request (the customer is the only input). An exception is a
 * refusal (fail closed).
 */
interface AccountUsabilityCheckerInterface
{
    public function check(CustomerInterface $customer): AccountUsability;
}
