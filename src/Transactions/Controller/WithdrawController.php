<?php

namespace App\Transactions\Controller;

use App\Account\Repository\AccountRepository;
use App\Transactions\DTO\TransactionRequest;
use App\Transactions\Entity\Transaction;
use App\Transactions\Enum\TransactionType;
use App\Transactions\Form\WithdrawForm;
use App\Transactions\Service\TransactionManager;
use App\Transactions\Service\TransactionService;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class WithdrawController extends AbstractController {
    #[Route('/withdraw', name: 'withdraw')]
    #[IsGranted('ROLE_CUSTOMER')]
    public function MakeWithdrawal(
        Request            $request,
        SessionInterface   $session,
        TransactionManager $transactionManager,
        AccountRepository  $accountRepository
    ): Response {

        $accountId = $session->get('bank_account_id');
        $sourceAccount = $accountRepository->find($accountId);

        $transaction = new Transaction();
        $transaction->setSourceAccount($sourceAccount);

        $form = $this->createForm(WithdrawForm::class, $transaction);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            $amount = $form->get('amount')->getData();

            $requestDTO = new TransactionRequest(
                $amount,
                $user,
                $sourceAccount,
                null,
                TransactionType::WITHDRAWAL
            );

            try {
                $transactionManager->handle($requestDTO);
                $this->addFlash('success', 'Transaction effectuée avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la transaction : ' . $e->getMessage());
            }

        return $this->redirectToRoute('account', [
            'accountId' => $accountId,
        ]);
    }
    return $this->render('@Transactions/withdraw.html.twig', [
        'form' => $form->createView(),
        'account' => $sourceAccount

    ]);
}

}

