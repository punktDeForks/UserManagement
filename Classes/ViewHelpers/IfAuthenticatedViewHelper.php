<?php
namespace Sandstorm\UserManagement\ViewHelpers;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Authentication\TokenInterface;
use Neos\Flow\Security\Context;
use Neos\FluidAdaptor\Core\ViewHelper\AbstractConditionViewHelper;
use Neos\FluidAdaptor\Core\ViewHelper\Exception;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

class IfAuthenticatedViewHelper extends AbstractConditionViewHelper
{

    /**
     * @throws Exception
     */
    public function initializeArguments(): void
    {
        $this->registerArgument('authenticationProviderName', 'string', 'authentication provider name to check', false, 'Sandstorm.UserManagement:Login');
    }


    /**
     * Renders <f:then> child if any account is currently authenticated, otherwise renders <f:else> child.
     *
     * @return string the rendered string
     * @api
     */
    public function render(): string
    {
        if (static::evaluateCondition($this->arguments, $this->renderingContext)) {
            return $this->renderThenChild();
        }

        return $this->renderElseChild();
    }

    /**
     * @param array $arguments
     * @param RenderingContextInterface $renderingContext
     * @return bool
     */
    protected static function evaluateCondition($arguments = null, RenderingContextInterface $renderingContext)
    {
        $objectManager = $renderingContext->getObjectManager();
        /** @var Context $securityContext */
        $securityContext = $objectManager->get(Context::class);
        $activeTokens = $securityContext->getAuthenticationTokens();


        /** @var $token TokenInterface */
        foreach ($activeTokens as $token) {
            if ($token->getAuthenticationProviderName() === $arguments['authenticationProviderName'] && $token->isAuthenticated()) {
                return true;
            }
        }
        return false;
    }
}
