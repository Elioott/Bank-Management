<?php

namespace App\Transactions\Controller;

use App\Account\Repository\AccountRepository;
use App\Transactions\Entity\Transaction;
use App\Transactions\Enum\TransactionStatus;
use App\Transactions\Enum\TransactionType;
use App\Transactions\Form\TransferForm;
use App\Transactions\Service\TransactionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TransferController extends AbstractController
{
    #[Route('/transfer', name: 'transfer')]
    #[IsGranted('ROLE_CUSTOMER')] 

    public function MakeTransfer(Request                $request,
                                 AccountRepository      $accountRepository,
                                 TransactionService     $transactionService,
                                 EntityManagerInterface $entityManager,
                            ): Response
    {
        $user = $this->getUser();
        
        $accounts = $accountRepository->findBy(['owner' => $user]);
    
        $transaction = new Transaction();
    
        $form = $this->createForm(TransferForm::class, $transaction, [
            'user' => $user,
            'accounts' => $accounts,
        ]);
    
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            $sourceAccount = $form->get('source_account')->getData();
            $destinationAccountNumber = $form->get('destination_account_number')->getData();
            $amount = $form->get('amount')->getData();
    
            if ($sourceAccount === null) {

                throw $this->createNotFoundException('Source account not found.');
            }
    
            $destinationAccount = $accountRepository->findOneBy(['account_number' => $destinationAccountNumber]);
    
            if ($destinationAccount === null) {

                throw $this->createNotFoundException('Destination account not found.');
            }
    
            if ($sourceAccount->getOwner() !== $user) {

                throw $this->createAccessDeniedException('You do not own the source account.');

            }
    

            if (!$sourceAccount->isActive()) {
                throw new AccessDeniedException('Le compte source est inactif. Transaction refusée.');
            }

            if (!$destinationAccount->isActive()) {
                throw new AccessDeniedException('Le compte destination est inactif. Transaction refusée.');
            }

            if (!$destinationAccount->canDeposit($amount)) {
                $transactionService->createFailedTransaction($amount, $sourceAccount, $destinationAccount, TransactionType::TRANSFER , $entityManager);

                throw $this->createAccessDeniedException('Transfer denied: the savings account has exceeded its deposit limit of 25,000.');
            }

            $transactionService->processTransaction($amount, $sourceAccount, $destinationAccount, TransactionType::TRANSFER);

            return $this->redirectToRoute('account', [
                'accountId' => $sourceAccount->getId(),
            ]);
        }
    
        return $this->render('@Transactions/transfer.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
