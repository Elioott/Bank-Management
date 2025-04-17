<?php

namespace App\Transactions\Controller;

use App\Account\Repository\AccountRepository;
use App\Transactions\DTO\TransactionRequest;
use App\Transactions\Entity\Transaction;
use App\Transactions\Enum\TransactionType;
use App\Transactions\Form\DepositForm;
use App\Transactions\Service\TransactionManager;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


final class DepositController extends AbstractController {
    #[Route('/deposit', name: 'deposit')]
    #[IsGranted('ROLE_CUSTOMER')]
    public function MakeDeposit(
        Request                $request,
        SessionInterface       $session,
        TransactionManager     $transactionManager,
        AccountRepository      $accountRepository
    ): Response {

        $bankAccountId = $session->get('bank_account_id');
        $bankAccount = $accountRepository->find($bankAccountId);

    $form = $this->createForm(DepositForm::class, new Transaction());

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $user = $this->getUser();
        $amount = $form->get('amount')->getData();

        $requestDTO = new TransactionRequest(
            $amount,
            $user,
            null,
            $bankAccount,
            TransactionType::DEPOSIT
        );

        try {
            $transactionManager->handle($requestDTO);
            $this->addFlash('success', 'Transaction effectuée avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la transaction : ' . $e->getMessage());
        }
        return $this->redirectToRoute('account', [
            'accountId' => $bankAccount->getId(),
        ]);
    }
    return $this->render('@Transactions/deposit.html.twig', [
        'form' => $form->createView(),
        'account' => $bankAccount
    ]);
}
}