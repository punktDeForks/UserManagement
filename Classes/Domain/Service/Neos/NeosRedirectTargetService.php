<?php
namespace Sandstorm\UserManagement\Domain\Service\Neos;

use Neos\Flow\Mvc\Exception\NoSuchArgumentException;
use Neos\Flow\Mvc\Routing\Exception\MissingActionNameException;
use Neos\Neos\Exception;
use Sandstorm\UserManagement\Domain\Service\RedirectTargetServiceInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Neos\Service\LinkingService;

class NeosRedirectTargetService implements RedirectTargetServiceInterface
{

    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @Flow\InjectConfiguration(path="redirect.afterLogin")
     * @var string
     */
    protected $redirectAfterLogin;

    /**
     * @Flow\InjectConfiguration(path="redirect.afterLogout")
     * @var string
     */
    protected $redirectAfterLogout;

    /**
     * @param ControllerContext $controllerContext
     * @param ActionRequest|null $originalRequest
     * @return ActionRequest|NULL|string
     * @throws NoSuchArgumentException
     * @throws MissingActionNameException
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     * @throws Exception
     */
    public function onAuthenticationSuccess(ControllerContext $controllerContext, ActionRequest $originalRequest = null)
    {
        // Check if config for redirect is done
        if (is_array($this->redirectAfterLogin)
            && array_key_exists('action', $this->redirectAfterLogin)
            && array_key_exists('controller', $this->redirectAfterLogin)
            && array_key_exists('package', $this->redirectAfterLogin)
        ) {
            $controllerArguments = [];
            if (array_key_exists('controllerArguments', $this->redirectAfterLogin) &&
                is_array($this->redirectAfterLogin['controllerArguments'])
            ) {
                $controllerArguments = $this->redirectAfterLogin['controllerArguments'];
            }

            return $controllerContext->getUriBuilder()
                ->reset()
                ->setCreateAbsoluteUri(true)
                ->uriFor($this->redirectAfterLogin['action'], $controllerArguments,
                    $this->redirectAfterLogin['controller'], $this->redirectAfterLogin['package']);
        }

        // Neos only logic (configuration at node or via TS)
        /** @var ActionRequest $actionRequest */
        $actionRequest = $controllerContext->getRequest();

        if ($actionRequest->hasArgument('forwardUrl') && strlen($actionRequest->getArgument('forwardUrl'))) {
            return $this->sanitizeForwardUrl($actionRequest->getArgument('forwardUrl'));
        } elseif ($actionRequest->getInternalArgument('__redirectAfterLogin')) {
            return $this->getNodeLinkingService()
                ->createNodeUri($controllerContext, $actionRequest->getInternalArgument('__redirectAfterLogin'));
        }

        return  null;
    }

    public function onLogout(ControllerContext $controllerContext)
    {
        // Check if config for redirect is done
        if (is_array($this->redirectAfterLogout)
            && array_key_exists('action', $this->redirectAfterLogout)
            && array_key_exists('controller', $this->redirectAfterLogout)
            && array_key_exists('package', $this->redirectAfterLogout)
        ) {
            $controllerArguments = [];
            if (array_key_exists('controllerArguments', $this->redirectAfterLogout) &&
                is_array($this->redirectAfterLogout['controllerArguments'])
            ) {
                $controllerArguments = $this->redirectAfterLogout['controllerArguments'];
            }

            return $controllerContext->getUriBuilder()
                ->reset()
                ->setCreateAbsoluteUri(true)
                ->uriFor($this->redirectAfterLogout['action'], $controllerArguments,
                    $this->redirectAfterLogout['controller'], $this->redirectAfterLogout['package']);
        }

        // Neos only logic (configuration at node or via TS)
        /** @var ActionRequest $actionRequest */
        $actionRequest = $controllerContext->getRequest();
        if ($actionRequest->getInternalArgument('__redirectAfterLogout')) {
            return $this->getNodeLinkingService()
                ->createNodeUri($controllerContext, $actionRequest->getInternalArgument('__redirectAfterLogout'));
        }
    }

    /**
     * @return LinkingService
     */
    protected function getNodeLinkingService()
    {
        return $this->objectManager->get(LinkingService::class);
    }

    /**
     * @param string $forwardUrl
     * @return string
     */
    protected function sanitizeForwardUrl(string $forwardUrl): string {
        return filter_var($forwardUrl, FILTER_SANITIZE_URL);
    }
}
