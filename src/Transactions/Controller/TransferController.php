<?php

namespace App\Transactions\Controller;

use App\Transactions\DTO\TransactionRequest;
use App\Transactions\Service\TransactionManager;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Account\Repository\AccountRepository;
use App\Beneficiary\Entity\Beneficiary;
use App\Transactions\Entity\Transaction;
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
    public function MakeTransfer(
        Request                $request,
        AccountRepository      $accountRepository,
        TransactionManager     $transactionManager,
        EntityManagerInterface $entityManager,
        SessionInterface       $session,
    ): Response {

        $user = $this->getUser();
        $bankAccountId = $session->get('bank_account_id');
        $userAccounts = $accountRepository->findBy(['owner' => $user]);
        $userBeneficiaries = $entityManager->getRepository(Beneficiary::class)->findBy(['member' => $user]);

        $form = $this->createForm(TransferForm::class, new Transaction(), [
            'user' => $user,
            'bank_accounts' => $userAccounts,
            'beneficiaries' => $userBeneficiaries,
        ]);

        $form->handleRequest($request);
        $sourceAccount =$accountRepository->find($bankAccountId) ;

        if ($form->isSubmitted() && $form->isValid()) {
            $destinationAccountNumber = $form->get('destination_account_number')->getData()->getBankAccountNumber();
            $destinationAccount = $accountRepository->findOneBy(['account_number' => $destinationAccountNumber]);
            $amount = $form->get('amount')->getData();
        
            $requestDTO = new TransactionRequest(
                $amount,
                $user,
                $sourceAccount,
                $destinationAccount,
                TransactionType::TRANSFER
            );

            try {
                $transactionManager->handle($requestDTO);
                $this->addFlash('success', 'Transaction effectuée avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la transaction : ' . $e->getMessage());
            }

            return $this->redirectToRoute('account', [
                'accountId' => $sourceAccount->getId(),
            ]);
        }

        return $this->render('@Transactions/transfer.html.twig', [
            'form' => $form->createView(),
            'account'=>$sourceAccount

        ]);
    }
}
