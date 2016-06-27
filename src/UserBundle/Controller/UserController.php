<?php

namespace UserBundle\Controller;

use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Util\Codes;
use FOS\UserBundle\FOSUserEvents;
use FOS\UserBundle\Event\FormEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use CoreBundle\Controller\CoreController;
use UserBundle\Entity\User;
use UserBundle\Event\UserCreateEvent;
use UserBundle\Form\Type\UserType;
use UserBundle\Form\Type\UserEditType;

class UserController extends CoreController
{
    /**
     * @View(serializerGroups={"Default", "details-user", "me"}, statusCode=201)
     */
    public function postUserAction(Request $request)
    {
        /** @var \FOS\UserBundle\Doctrine\UserManager $userManager */
        $userManager = $this->get('fos_user.user_manager');
        /** @var \UserBundle\Entity\User $user */
        $user = $userManager->createUser();

        return $this->formUser($user, $request, false);
    }

    /**
     * @View(serializerGroups={"Default", "details-user", "me"})
     * @Post("/me")
     */
    public function postMeAction(Request $request) // post because no file upload with PATCH
    {
        $user = $this->getUser();

        return $this->formUser($user, $request, true);
    }

    private function formUser(User $user, Request $request, $isEdit = false)
    {
        $formType = !$isEdit ? UserType::class : UserEditType::class;

        $form = $this->createForm($formType, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if (!$isEdit) {
                $event = new UserCreateEvent($user);

                $this->dispatch(UserCreateEvent::NAME, $event);
            }

            $user = $this->persistUser($user);

            /** @var \Vich\UploaderBundle\Templating\Helper\UploaderHelper $helper */
            $helper = $this->container->get('vich_uploader.templating.helper.uploader_helper');
            $path = $helper->asset($user, 'image');

            return [
                'user' => $user,
                'image_link' => $path,
            ];
        }

        return new JsonResponse($this->getAllErrors($form), Codes::HTTP_BAD_REQUEST);
    }

    /**
     * @Post("/forget-password")
     */
    public function postForgetPasswordAction(Request $request)
    {
        $username = $request->request->get('username');

        if (is_null($username)) {
            return new JsonResponse(['error' => $this->t('user.error.forget_password.empty_username')], Codes::HTTP_BAD_REQUEST);
        }

        /** @var $user \FOS\UserBundle\Model\UserInterface */
        $user = $this->get('fos_user.user_manager')->findUserByUsernameOrEmail($username);

        if (null === $user) {
            throw $this->createNotFoundException();
        }

        if ($user->isPasswordRequestNonExpired($this->getParameter('fos_user.resetting.token_ttl'))) {
            return new JsonResponse(['error' => $this->t('resetting.password_already_requested', 'FOSUserBundle')], Codes::HTTP_CONFLICT);
        }

        if (null === $user->getConfirmationToken()) {
            /** @var $tokenGenerator \FOS\UserBundle\Util\TokenGeneratorInterface */
            $tokenGenerator = $this->get('fos_user.util.token_generator');
            $user->setConfirmationToken($tokenGenerator->generateToken());
        }

        $this->get('fos_user.mailer')->sendResettingEmailMessage($user);
        $user->setPasswordRequestedAt(new \DateTime());
        $this->get('fos_user.user_manager')->updateUser($user, true);

        return new JsonResponse([], Codes::HTTP_NO_CONTENT);
    }

    /**
     * @Post("/reset-password/{token}")
     */
    public function postResetPasswordAction(Request $request, $token)
    {
        /** @var $userManager \FOS\UserBundle\Model\UserManagerInterface */
        $userManager = $this->get('fos_user.user_manager');

        /** @var \UserBundle\Entity\User $user */
        $user = $userManager->findUserByConfirmationToken($token);

        if (null === $user) {
            throw $this->createNotFoundException();
        }

        if (!$user->isPasswordRequestNonExpired($this->getParameter('fos_user.resetting.token_ttl'))) {
            return new JsonResponse(['error' => $this->t('resetting.password_request_expired')], Codes::HTTP_GONE);
        }

        $form = $this->createForm(UserEditType::class, $user, ['method' => 'post']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            /** @var $dispatcher \Symfony\Component\EventDispatcher\EventDispatcherInterface */
            $dispatcher = $this->get('event_dispatcher');

            $event = new FormEvent($form, $request);
            $dispatcher->dispatch(FOSUserEvents::RESETTING_RESET_SUCCESS, $event);

            return [
                'user' => $this->persistUser($user),
            ];
        }

        return new JsonResponse($this->getAllErrors($form), Codes::HTTP_BAD_REQUEST);
    }

    /**
     * @param User $userModel
     *
     * @return \UserBundle\Entity\User
     */
    private function persistUser(User $user)
    {
        /** @var \FOS\UserBundle\Doctrine\UserManager $userManager */
        $userManager = $this->get('fos_user.user_manager');

        $user->setEnabled(true);
        $userManager->updateUser($user, true);

        return $user;
    }

    /**
     * @return string
     */
    protected function getRepositoryName()
    {
        return 'CoreBundle:User';
    }
}
