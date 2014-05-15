<?php

namespace Etu\Module\CovoitBundle\Controller;

use Doctrine\ORM\EntityManager;
use Etu\Core\CoreBundle\Framework\Definition\Controller;
use Etu\Module\CovoitBundle\Entity\Covoit;
use Symfony\Component\HttpFoundation\Request;

// Import annotations
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

/**
 * @Route("/carpooling/my-proposals")
 * @Template()
 */
class PrivateController extends Controller
{
    /**
     * @Route("/{page}", defaults={"page" = 1}, requirements={"page" = "\d+"}, name="covoiturage_my_index")
     * @Template()
     */
    public function indexAction($page = 1)
    {
        if (! $this->getUserLayer()->isUser()) {
            return $this->createAccessDeniedResponse();
        }

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        $query = $em->createQueryBuilder()
            ->select('c, cs, ca')
            ->from('EtuModuleCovoitBundle:Covoit', 'c')
            ->leftJoin('c.author', 'ca')
            ->leftJoin('c.subscriptions', 'cs')
            ->where('c.author = :user')
            ->orWhere('cs.user = :user')
            ->setParameter('user', $this->getUser()->getId())
            ->orderBy('c.date', 'DESC')
            ->getQuery();

        /** @var Covoit[] $covoits */
        $covoits = $this->get('knp_paginator')->paginate($query, $page, 40);

        return [
            'pagination' => $covoits,
            'today' => new \DateTime()
        ];
    }

    /**
     * @Route("/propose", name="covoiturage_my_propose")
     * @Template()
     */
    public function proposeAction(Request $request)
    {
        if (! $this->getUserLayer()->isUser()) {
            return $this->createAccessDeniedResponse();
        }

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        $proposal = new Covoit();
        $proposal->setAuthor($this->getUser());
        $proposal->setStartCity($em->getRepository('EtuCoreBundle:City')->find(749)); // Troyes
        $proposal->setEndCity($em->getRepository('EtuCoreBundle:City')->find(826)); // Paris

        if ($this->getUser()->getPhoneNumber()) {
            $proposal->setPhoneNumber($this->getUser()->getPhoneNumber());
        }

        $form = $this->createForm($this->get('etu.covoit.form.proposal'), $proposal);

        if ($request->getMethod() == 'POST' && $form->submit($request)->isValid()) {
            $proposal->setStartHour($proposal->getStartHour()->format('H:i'));
            $proposal->setEndHour($proposal->getEndHour()->format('H:i'));

            $em->persist($proposal);
            $em->flush();

            $this->get('session')->getFlashBag()->set('message', array(
                'type' => 'success',
                'message' => 'covoit.messages.created'
            ));

            return $this->redirect($this->generateUrl('covoiturage_view', [
                'id' => $proposal->getId(),
                'slug' => $proposal->getStartCity()->getSlug() . '-' . $proposal->getEndCity()->getSlug()
            ]));
        }

        return [
            'form' => $form->createView()
        ];
    }
}