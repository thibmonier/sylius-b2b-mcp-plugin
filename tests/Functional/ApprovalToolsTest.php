<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Functional;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Scopes;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Factory\CompanyMemberFactoryInterface;
use Tmonier\SyliusPurchaseApprovalPlugin\Entity\MemberLimitInterface;

/**
 * Purchase approvals (tmonier/sylius-purchase-approval-plugin): list_pending_approvals, the
 * approval in get_order, and decide_approval (enabled in the test application, opt-in).
 */
final class ApprovalToolsTest extends WebTestCase
{
    use B2bMcpTestTrait;

    private const WITH_APPROVE = [Scopes::READ, Scopes::CART, Scopes::APPROVE];

    private CustomerInterface $buyer;

    private CustomerInterface $approver;

    protected function setUp(): void
    {
        $this->setUpClient();
        $this->buyer = $this->customer('Bob', 'Buyer');
        $this->approver = $this->customer('Ann', 'Approver');
        $company = $this->company([$this->buyer, CompanyMemberInterface::ROLE_BUYER], [$this->approver, CompanyMemberInterface::ROLE_APPROVER]);
        // Orders of the buyer above 10.00 need an approval.
        $this->limit($company, $this->buyer, 1000, null);
    }

    protected function tearDown(): void
    {
        $this->tearDownClient();
        parent::tearDown();
    }

    public function testTheApproverListsThePendingApprovalsOfTheCompany(): void
    {
        $order = $this->order($this->buyer);

        $result = $this->callTool($this->accessToken($this->approver), 'list_pending_approvals');

        self::assertSame(1, $result['total']);
        self::assertSame($order->getNumber(), self::at($result, 'approvals', 0, 'order_number'));
        self::assertSame('Bob Buyer', self::at($result, 'approvals', 0, 'placed_by'));
        self::assertSame('10.00', self::at($result, 'approvals', 0, 'order_limit'));
        self::assertTrue(self::at($result, 'approvals', 0, 'you_can_approve'));

        $this->mcpSessionId = null;
        $refused = $this->callTool($this->accessToken($this->buyer), 'list_pending_approvals');
        self::assertSame('Only the approvers of a company can see its pending approvals.', $refused['error']);
    }

    public function testTheBuyerSeesTheApprovalOfTheirOrder(): void
    {
        $order = $this->order($this->buyer);

        $detail = $this->callTool($this->accessToken($this->buyer), 'get_order', ['order_number' => (string) $order->getNumber()]);

        self::assertSame('pending', self::at($detail, 'approval', 'state'));
        self::assertSame('10.00', self::at($detail, 'approval', 'order_limit'));
        self::assertNull(self::at($detail, 'approval', 'decided_at'));
    }

    public function testTheApproverApprovesWithTheApproveScopeOnly(): void
    {
        $order = $this->order($this->buyer);
        $arguments = ['order_number' => (string) $order->getNumber(), 'decision' => 'approve', 'comment' => 'OK for this month', 'idempotency_key' => 'approve-key-0001'];

        $withoutScope = $this->callTool($this->accessToken($this->approver), 'decide_approval', $arguments);
        self::assertStringContainsString('was not granted the "b2b:approve" permission', self::stringAt($withoutScope, 'error'));
        self::assertSame('pending', $this->approvalState($order));

        $this->mcpSessionId = null;
        $token = $this->accessToken($this->approver, self::WITH_APPROVE);
        $result = $this->callTool($token, 'decide_approval', $arguments);
        self::assertSame('approved', $result['state']);
        self::assertSame('approved', $this->approvalState($order));
        self::assertSame('Ann Approver', $this->sqlValue('SELECT decider_name FROM tmonier_purchase_approval_order_approval a INNER JOIN sylius_order o ON o.id = a.order_id WHERE o.number = ?', [$order->getNumber()]));

        // The same decision retried: replayed, nothing decided twice.
        self::assertTrue($this->callTool($token, 'decide_approval', $arguments)['replayed']);
    }

    public function testRejectingCancelsTheOrder(): void
    {
        $order = $this->order($this->buyer);

        $result = $this->callTool($this->accessToken($this->approver, self::WITH_APPROVE), 'decide_approval', ['order_number' => (string) $order->getNumber(), 'decision' => 'reject', 'idempotency_key' => 'reject-key-0001']);

        self::assertSame('rejected', $result['state']);
        self::assertSame('cancelled', $result['order_state']);
    }

    public function testTheRulesOfTheShopApply(): void
    {
        $company = $this->companyOf($this->approver);
        // Above the approval limit of the approver.
        $this->limit($company, $this->approver, 1000, 2000);
        $order = $this->order($this->buyer);
        $token = $this->accessToken($this->approver, self::WITH_APPROVE);
        $aboveLimit = $this->callTool($token, 'decide_approval', ['order_number' => (string) $order->getNumber(), 'decision' => 'approve', 'idempotency_key' => 'above-limit-1']);
        self::assertArrayHasKey('error', $aboveLimit);
        self::assertSame('pending', $this->approvalState($order));
        // Rejecting is allowed above the limit (as in the shop).
        self::assertSame('rejected', $this->callTool($token, 'decide_approval', ['order_number' => (string) $order->getNumber(), 'decision' => 'reject', 'idempotency_key' => 'above-limit-2'])['state']);

        // No self-approval.
        $own = $this->order($this->approver);
        $self = $this->callTool($token, 'decide_approval', ['order_number' => (string) $own->getNumber(), 'decision' => 'approve', 'idempotency_key' => 'self-approve-1']);
        self::assertArrayHasKey('error', $self);
        self::assertSame('pending', $this->approvalState($own));

        // The buyer cannot decide, even with the scope.
        $this->mcpSessionId = null;
        $buyerResult = $this->callTool($this->accessToken($this->buyer, self::WITH_APPROVE), 'decide_approval', ['order_number' => (string) $own->getNumber(), 'decision' => 'approve', 'idempotency_key' => 'buyer-approve-1']);
        self::assertStringStartsWith('No approval of order', self::stringAt($buyerResult, 'error'));
    }

    public function testAnApproverOfAnotherCompanyCannotDecide(): void
    {
        $order = $this->order($this->buyer);
        $outsider = $this->customer('Eve', 'Outsider');
        $this->company([$outsider, CompanyMemberInterface::ROLE_APPROVER]);

        $result = $this->callTool($this->accessToken($outsider, self::WITH_APPROVE), 'decide_approval', ['order_number' => (string) $order->getNumber(), 'decision' => 'approve', 'idempotency_key' => 'outsider-key-1']);

        self::assertStringStartsWith('No approval of order', self::stringAt($result, 'error'));
        self::assertSame('pending', $this->approvalState($order));
    }

    public function testAnOrderOfAnotherChannelCannotBeDecidedHere(): void
    {
        $order = $this->order($this->buyer);
        $order->setChannel($this->otherChannel());
        $this->entityManager()->flush();

        $result = $this->callTool($this->accessToken($this->approver, self::WITH_APPROVE), 'decide_approval', ['order_number' => (string) $order->getNumber(), 'decision' => 'approve', 'idempotency_key' => 'channel-key-01']);

        self::assertStringStartsWith('No approval of order', self::stringAt($result, 'error'));
        self::assertSame('pending', $this->approvalState($order));
    }

    private function approvalState(OrderInterface $order): mixed
    {
        return $this->sqlValue('SELECT a.state FROM tmonier_purchase_approval_order_approval a INNER JOIN sylius_order o ON o.id = a.order_id WHERE o.number = ?', [$order->getNumber()]);
    }

    /** @param array{CustomerInterface, string} ...$members */
    private function company(array ...$members): CompanyInterface
    {
        /** @var FactoryInterface<CompanyInterface> $factory */
        $factory = $this->service('tmonier_company_accounts.factory.company');
        $company = $factory->createNew();
        $company->setCode('MCP_' . strtoupper(bin2hex(random_bytes(4))));
        $company->setName('Acme Industries');
        $this->entityManager()->persist($company);
        $this->entityManager()->flush();
        /** @var CompanyMemberFactoryInterface $memberFactory */
        $memberFactory = $this->service('tmonier_company_accounts.factory.company_member');
        foreach ($members as [$customer, $role]) {
            $this->entityManager()->persist($memberFactory->createForCustomer($company, $this->managed($customer), $role));
        }
        $this->entityManager()->flush();

        return $company;
    }

    private function companyOf(CustomerInterface $customer): CompanyInterface
    {
        $member = $this->entityManager()->getRepository(CompanyMemberInterface::class)->findOneBy(['customer' => $this->managed($customer)]);
        self::assertInstanceOf(CompanyMemberInterface::class, $member);
        $company = $member->getCompany();
        self::assertNotNull($company);

        return $company;
    }

    private function limit(CompanyInterface $company, CustomerInterface $customer, ?int $orderLimit, ?int $approvalLimit): void
    {
        $member = $this->entityManager()->getRepository(CompanyMemberInterface::class)->findOneBy(['customer' => $this->managed($customer), 'company' => $this->managed($company)]);
        self::assertInstanceOf(CompanyMemberInterface::class, $member);
        $limit = $this->entityManager()->getRepository(MemberLimitInterface::class)->findOneBy(['member' => $member]);
        if (!$limit instanceof MemberLimitInterface) {
            /** @var FactoryInterface<MemberLimitInterface> $factory */
            $factory = $this->service('tmonier_purchase_approval.factory.member_limit');
            $limit = $factory->createNew();
            $limit->setMember($member);
            $this->entityManager()->persist($limit);
        }
        $limit->setOrderLimit($orderLimit);
        $limit->setApprovalLimit($approvalLimit);
        $limit->setCurrencyCode($this->channel()->getBaseCurrency()?->getCode());
        $this->entityManager()->flush();
    }
}
