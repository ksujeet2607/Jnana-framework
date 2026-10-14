<?php

namespace Jnana\Factory;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FormFactory
{
    public function csrfTokenManager(): CsrfTokenManager{
       return new CsrfTokenManager();
    }

    public function validator(): ValidatorInterface{
        return Validation::createValidator();
    }
    public function formFactory() : FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension($this->csrfTokenManager()))
            ->addExtension(new ValidatorExtension($this->validator()))
            ->addExtension(new HttpFoundationExtension())
            ->getFormFactory();
    }

}