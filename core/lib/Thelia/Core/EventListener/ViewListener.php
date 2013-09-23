<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Core\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Router;
use Thelia\Core\Template\Exception\ResourceNotFoundException;
use Thelia\Core\Template\ParserInterface;
use Thelia\Exception\OrderException;
use Thelia\Tools\Redirect;
use Thelia\Tools\URL;
use Thelia\Core\Security\Exception\AuthenticationException;

/**
 *
 * ViewSubscriber Main class subscribing to view http response.
 *
 * @TODO Look if it's possible to block this definition in dependency-injection
 *
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */

class ViewListener implements EventSubscriberInterface
{
    /**
     *
     * @var Symfony\Component\DependencyInjection\ContainerInterface
     */
    private $container;

    /**
     *
     * @param \Thelia\Core\Template\ParserInterface $parser
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     *
     * Launch the parser defined on the constructor and get the result.
     *
     * The result is transform id needed into a Response object
     *
     * @param \Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent $event
     */
    public function onKernelView(GetResponseForControllerResultEvent $event)
    {

        $parser = $this->container->get('thelia.parser');

        try {
            $content = $parser->getContent();

            if ($content instanceof Response) {
                $response = $content;$event->setResponse($content);
            } else {
                $response = new Response($content, $parser->getStatus() ?: 200);
            }

            $response->setCache(array(
                'last_modified' => new \DateTime(),
                'max_age'       => 600,
                's_maxage'      => 600,
                'private'       => false,
                'public'        => true,
            ));

            $event->setResponse($response);
        } catch (ResourceNotFoundException $e) {
            $event->setResponse(new Response($e->getMessage(), 404));
        } catch (AuthenticationException $ex) {

            // Redirect to the login template
            Redirect::exec($this->container->get('thelia.url.manager')->viewUrl($ex->getLoginTemplate()));
        } catch (OrderException $e) {
            switch($e->getCode()) {
                case OrderException::CART_EMPTY:
                    // Redirect to the cart template
                    Redirect::exec($this->container->get('router.chainRequest')->generate($e->cartRoute, $e->arguments, Router::ABSOLUTE_URL));
                    break;
                case OrderException::UNDEFINED_DELIVERY:
                    // Redirect to the delivery choice template
                    Redirect::exec($this->container->get('router.chainRequest')->generate($e->orderDeliveryRoute, $e->arguments, Router::ABSOLUTE_URL));
                    break;
            }

            throw $e;
        }
    }

    public function beforeKernelView(GetResponseForControllerResultEvent $event)
    {
        $request = $this->container->get('request');

        if (null === $view = $request->attributes->get('_view')) {
            $request->attributes->set('_view', $this->findView($request));
        }

    }

    public function findView(Request $request)
    {
        if (! $view = $request->query->get('view')) {
            $view = "index";
            if ($request->request->has('view')) {
                $view = $request->request->get('view');
            }
        }

        return $view;
    }


    /**
     *
     * Register the method to execute in this class for a specific event (here the kernel.view event)
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::VIEW =>array(
                array('onKernelView', 0),
                array('beforeKernelView', 5)
            )
        );
    }
}
