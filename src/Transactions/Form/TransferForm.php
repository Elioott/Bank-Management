<?php

namespace App\Transactions\Form;

use App\Transactions\Entity\Transaction;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransferForm extends AbstractType {
    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $builder
            ->add('destination_account_number', ChoiceType::class, [
                'choices' => $options['beneficiaries'],
                'choice_label' => function ($beneficiary) {
                    return $beneficiary->getName() . ' - ' . $beneficiary->getBankAccountNumber();
                },
                'mapped' => false,
                'label' => 'Compte destinataire',
            ])
            ->add('amount', MoneyType::class, [
                'currency' => 'EUR',
                'label' => 'Montant',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void {
        $resolver->setDefaults([
            'data_class' => Transaction::class,
            'bank_accounts' => [],
            'beneficiaries' => [],
            'user' => null,
        ]);
    }
}
