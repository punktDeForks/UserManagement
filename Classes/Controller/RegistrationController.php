<?php
namespace Sandstorm\UserManagement\Controller;

use Sandstorm\TemplateMailer\Domain\Service\EmailService;
use Sandstorm\UserManagement\Domain\Model\RegistrationFlow;
use Sandstorm\UserManagement\Domain\Repository\RegistrationFlowRepository;
use Sandstorm\UserManagement\Domain\Service\UserCreationServiceInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;

/**
 * Do the actual registration of new users
 */
class RegistrationController extends ActionController
{

    /**
     * @Flow\Inject
     * @var RegistrationFlowRepository
     */
    protected $registrationFlowRepository;

    /**
     * @Flow\Inject
     * @var UserCreationServiceInterface
     */
    protected $userCreationService;

    /**
     * @Flow\Inject
     * @var EmailService
     */
    protected $emailService;

    /**
     * @var string
     * @Flow\InjectConfiguration(path="email.subjectActivation")
     */
    protected $subjectActivation;


    /**
     * @Flow\SkipCsrfProtection
     */
    public function indexAction()
    {
        $this->view->assign('node', $this->request->getInternalArgument('__node'));
    }

    /**
     * @param RegistrationFlow $registrationFlow
     */
    public function registerAction(RegistrationFlow $registrationFlow)
    {
        // We remove already existing flows
        $alreadyExistingFlows = $this->registrationFlowRepository->findByEmail($registrationFlow->getEmail());
        if (count($alreadyExistingFlows) > 0) {
            foreach ($alreadyExistingFlows as $alreadyExistingFlow) {
                $this->registrationFlowRepository->remove($alreadyExistingFlow);
            }
        }
        $registrationFlow->storeEncryptedPassword();

        // Send out a confirmation mail
        $activationLink = $this->uriBuilder->reset()->setCreateAbsoluteUri(true)->uriFor(
            'activateAccount',
            ['token' => $registrationFlow->getActivationToken()],
            'Registration');

        $this->emailService->sendTemplateEmail(
            'ActivationToken',
            $this->subjectActivation,
            [$registrationFlow->getEmail()],
            [
                'activationLink' => $activationLink,
                'registrationFlow' => $registrationFlow
            ],
            'sandstorm_usermanagement_sender_email',
            [], // cc
            [], // bcc
            [], // attachments
            'sandstorm_usermanagement_replyTo_email'
        );

        $this->registrationFlowRepository->add($registrationFlow);

        $this->view->assign('registrationFlow', $registrationFlow);
        $this->view->assign('node', $this->request->getInternalArgument('__node'));
    }

    /**
     * @param string $token
     */
    public function activateAccountAction(string $token)
    {
        /* @var $registrationFlow \Sandstorm\UserManagement\Domain\Model\RegistrationFlow */
        $registrationFlow = $this->registrationFlowRepository->findOneByActivationToken($token);
        if (!$registrationFlow) {
            $this->view->assign('tokenNotFound', true);

            return;
        }

        if (!$registrationFlow->hasValidActivationToken()) {
            $this->view->assign('tokenTimeout', true);

            return;
        }

        $this->view->assignMultiple([
            'token' => $token
        ]);
    }

    /**
     * @param string $token
     */
    public function confirmAccountAction($token)
    {
        /* @var $registrationFlow \Sandstorm\UserManagement\Domain\Model\RegistrationFlow */
        $registrationFlow = $this->registrationFlowRepository->findOneByActivationToken($token);
        if (!$registrationFlow) {
            $this->view->assign('tokenNotFound', true);

            return;
        }

        if (!$registrationFlow->hasValidActivationToken()) {
            $this->view->assign('tokenTimeout', true);

            return;
        }

        $user = $this->userCreationService->createUserAndAccount($registrationFlow);
        $this->registrationFlowRepository->remove($registrationFlow);
        $this->persistenceManager->whitelistObject($registrationFlow);

        $this->view->assign('success', true);
        $this->view->assign('user', $user);
        $this->view->assign('node', $this->request->getInternalArgument('__node'));
    }

    /**
     * Disable the technical error flash message
     *
     * @return boolean
     */
    protected function getErrorFlashMessage()
    {
        return false;
    }
}
