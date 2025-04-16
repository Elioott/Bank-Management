<?php

namespace App\Account\Controller;

use App\Account\Entity\Account;
use App\Account\Enum\AccountStatus;
use App\Account\Enum\AccountType;
use App\Account\Form\AccountForm as BankAccountFormType;
use App\Account\Repository\AccountRepository;
use App\Account\Service\AccountService;
use App\Transactions\Entity\Transaction;
use App\Transactions\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class AccountController extends AbstractController
{
    private $accountService;

    public function __construct(AccountService $accountService)
    {
        $this->accountService = $accountService;
    }

    #[Route('/create', name: 'create_account', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CUSTOMER')]

public function create(Request $request, EntityManagerInterface $entityManager, AccountRepository $accountRepository): Response
{
    $accounts = $accountRepository->findBy(['owner' => $this->getUser()]);
    
    if (count($accounts) >= 5) {
        $this->addFlash('error', 'Vous ne pouvez avoir que 5 comptes maximum');
        return $this->redirectToRoute('accounts');
    }

    $form = $this->createForm(BankAccountFormType::class);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $accountType = $form->get('type')->getData();
        
        if ($accountType === AccountType::SAVINGS->value && !$this->hasValidCurrentAccount($accounts)) {
            $this->addFlash('error', 'Vous devez avoir un compte courant avec au moins 10€ pour ouvrir un compte épargne');
            return $this->redirectToRoute('create_account');
        }

        $request->getSession()->set('new_account_data', [
            'type' => $accountType
        ]);

        return $this->redirectToRoute('confirm_account');
    }

    return $this->render('@Account/createAccount.html.twig', [
        'form' => $form->createView(),
        'accounts' => $accounts
    ]);
}

#[Route('/confirm', name: 'confirm_account', methods: ['GET', 'POST'])]
#[IsGranted('ROLE_CUSTOMER')]

public function confirm(Request $request, EntityManagerInterface $entityManager, AccountRepository $accountRepository): Response
{
    $accountData = $request->getSession()->get('new_account_data');
    
    if (!$accountData) {
        return $this->redirectToRoute('create_account');
    }

    $accounts = $accountRepository->findBy(['owner' => $this->getUser()]);
    if ($accountData['type'] === AccountType::SAVINGS->value && !$this->hasValidCurrentAccount($accounts)) {
        $this->addFlash('error', 'Les conditions pour ouvrir un compte épargne ne sont plus remplies');
        return $this->redirectToRoute('create_account');
    }

    if ($request->isMethod('POST')) {
        $account = new Account();
        $account->setType($accountData['type']);
        $account->setOwner($this->getUser());
        $account->setAccountNumber((string)rand(1000000000, 9999999999));
        $account->setBalance(100); 
        $account->setStatus(AccountStatus::ACTIVE);

        $entityManager->persist($account);
        $entityManager->flush();

        $request->getSession()->remove('new_account_data');

        $this->addFlash('success', 'Votre compte a été créé avec succès ! 100€ ont été crédités.');
        return $this->redirectToRoute('accounts');
    }

    return $this->render('@Account/confirm.html.twig', [
        'account_type' => $accountData['type']
    ]);
}

private function hasValidCurrentAccount(array $bankAccounts): bool
{
    foreach ($bankAccounts as $account) {
        if ($account->getType() === AccountType::CURRENT && $account->getBalance() >= 10) {
            return true;
        }
    }
    return false;
}
    
    #[Route('/', name: 'accounts')]
    #[IsGranted('ROLE_CUSTOMER')]
    public function showUserAccounts(): Response
    {
        $user = $this->getUser();
        $bankAccounts = $this->accountService->getUserAccounts($user);

        return $this->render('@Account/accounts.html.twig', [
            'accounts' => $bankAccounts,
        ]);
    }

    #[Route('/{accountId}', name: 'account')]
    #[IsGranted('ROLE_CUSTOMER')]
    public function showAccountTransactions(int $accountId , SessionInterface $session, TransactionRepository $transactionRepository): Response
    {
        $transactions = $transactionRepository->findByAccount($accountId);

        $session->set('bank_account_id', $accountId);
     
        return $this->render('@Account/account.html.twig', [
            'transactions' => $transactions,
            'accountId' => $accountId,
        ]);
    }

    #[Route('/{accountId}/stats', name: 'account_stats', methods: ['GET'])]
    #[IsGranted('ROLE_CUSTOMER')]
    public function accountStats(int $accountId, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        
        $account = $entityManager->getRepository(Account::class)->find($accountId);
    
        if (!$account || $account->getOwner() !== $user) {
            throw $this->createAccessDeniedException('Vous ne pouvez voir que les comptes qui vous appartiennent.');
        }
    
        $transactionRepo = $entityManager->getRepository(Transaction::class);
        $expenses = $transactionRepo->findBy(['source_account' => $account]);
        $incomes = $transactionRepo->findBy(['destination_account' => $account]);
    
        $stats = [];
    
        foreach (array_merge($expenses, $incomes) as $transaction) {
            $month = $transaction->getDateTime()->format('Y-m');
    
            if (!isset($stats[$month])) {
                $stats[$month] = [
                    'income' => 0,
                    'expense' => 0,
                    'balance' => 0,
                ];
            }
    
            if ($transaction->getSourceAccount() === $account) {
                $stats[$month]['expense'] += $transaction->getAmount();
            }
    
            if ($transaction->getDestinationAccount() === $account) {
                $stats[$month]['income'] += $transaction->getAmount();
            }
    
            $stats[$month]['balance'] = $stats[$month]['income'] - $stats[$month]['expense'];
        }
    
        ksort($stats);
    
        $totalIncome = array_sum(array_column($stats, 'income'));
        $totalExpense = array_sum(array_column($stats, 'expense'));
        $totalMonths = count($stats);
        
        $avgIncome = $totalMonths > 0 ? $totalIncome / $totalMonths : 0;
        $avgExpense = $totalMonths > 0 ? $totalExpense / $totalMonths : 0;
    
        return $this->render('@Account/account_stats.html.twig', [
            'chartData' => json_encode($stats),
            'account' => $account,
            'avgIncome' => $avgIncome,
            'avgExpense' => $avgExpense,
            'currentBalance' => $stats ? end($stats)['balance'] : 0, 
            'monthlyIncome' => $totalIncome / $totalMonths, 
            'monthlyExpense' => $totalExpense / $totalMonths, 
            'savingsRate' => $totalIncome ? round((($totalIncome - $totalExpense) / $totalIncome) * 100, 2) : 0, 
        ]);
    }
    

    
}

