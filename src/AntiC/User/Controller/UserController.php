<?php

namespace AntiC\User\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use InvalidArgumentException;
use AntiC\User\Manager\UserManager;

/**
 * Controller with actions for handling form-based authentication and user management.
 *
 * @package SimpleUser
 */
class UserController
{
    /** @var UserManager */
    protected $userManager;

    /**
     * Constructor.
     *
     * @param UserManager $userManager
     * @param array $options
     */
    public function __construct(UserManager $userManager, $options = array())
    {
        $this->userManager = $userManager;

        if (!empty($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * Login action.
     *
     * @param Application $app
     * @param Request $request
     * @return Response
     */
    public function loginAction(Application $app, Request $request)
    {
        return $app['twig']->render('@user/authenticate.html.twig', array(
            'error' => $app['security.last_error']($request),
            'last_username' => $app['session']->get('_security.last_username'),
            'allowRememberMe' => isset($app['security.remember_me.response_listener']),
        ));
    }

    /**
     * View Self Action
     * 
     * @route /console/account
     * @param Application $app
     * @param Request $request
     * @return twig rendered template
     * @throws InvalidArgumentException if CMD on POST is invalid
     */
    public function viewAction(Application $app, Request $request) {
        $nameError;
        $passwordError;
        $emailError;
        if ($request->isMethod('POST')) {
            switch ($request->get("cmd")) {
                // Change Name Function
                case "changeName":
                    $user = $app['user'];
                    $user->setName($request->request->get('name'));
                    $nameError = $this->userManager->validate($user);
                    if (empty($nameError)) {
                        $this->userManager->update($user);
                        $app['session']->getFlashBag()->set('success', "Successfully updated name.");
                    }
                    break;
                // Change Email Function
                case "changeEmail":
                    $user = $app['user'];
                    if (password_verify($request->request->get('password'), $user->getPassword())) {
                        $user->setEmail($request->request->get('email'));
                        $emailError = $this->userManager->validate($user);
                        if (empty($emailError)) {
                            $this->userManager->update($user);
                            $app['session']->getFlashBag()->set('success', "Successfully updated email.");
                        }
                    } else {
                        $emailError = array("Provided password was invalid.");
                    }
                    break;
                // Change Password Function
                case "changePassword":
                    $user = $app['user'];
                    $currentPassword = $request->request->get('currentPassword');
                    $newPassword = $request->request->get('newPassword');
                    $confirmPassword = $request->request->get('confirmPassword');
                    if ($newPassword == $confirmPassword && !empty($confirmPassword)) {
                        if (password_verify($currentPassword, $user->getPassword())) {
                            $this->userManager->setUserPassword($user, $confirmPassword);
                            $passwordError = $this->userManager->validate($user);
                            if (empty($passwordError)) {
                                $this->userManager->update($user);
                                $app['session']->getFlashBag()->set('success', "Successfully updated password.");
                            }
                        } else {
                            $passwordError = array("Provided password was invalid.");
                        }
                    } else {
                        $passwordError = array("New Password and Confirm Password do not match.");
                    }
                    break;
                default:
                    throw new InvalidArgumentException("Command not correct.");
                    break;
            }
        }

        return $app['twig']->render('@user/account/settings.html.twig', array(
            'user' => $app['user'],
            'nameError' => $nameError,
            'emailError' => $emailError,
            'passwordError' => $passwordError
        ));
    }

    /**
     * Add User Action.
     *
     * @route /console/user/add
     * @param Application $app
     * @param Request $request
     * @return twig rendered template
     */
    public function addAction(Application $app, Request $request)
    {
        $user = $this->userManager->createUser('', '', '');

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirmPassword');
            if ($request->request->get('role')) {
                $role = array($request->request->get('role'));
            }

            $user->setName($request->request->get('userName'));
            $user->setEmail($request->request->get('email'));
            if (isset($role))
                $user->setRoles($role);
            if ($password == $confirmPassword && !empty($confirmPassword)) {
                $user = $this->userManager->createUser(
                    $request->request->get('email'), 
                    $confirmPassword, 
                    $request->request->get('userName')
                );

                if (isset($role))
                    $user->setRoles($role);

                $errors = $this->userManager->validate($user);
                if (empty($errors)) {
                    $this->userManager->insert($user);
                    $app['session']->getFlashBag()->set('success', "Successfully added user.");
                    return $app->redirect($app['url_generator']->generate('user.list'));
                }
            } else {
                $errors = array("Passwords do not match, or left blank.");
            }
        }

        return $app['twig']->render('@user/management/add.html.twig', array(
            'errors' => isset($errors) ? $errors : null,
            'user' => isset($user) ? $user : null,
        ));
    }

    /**
     * Edit user action.
     *
     * @param Application $app
     * @param Request $request
     * @param int $id
     * @return Response
     * @throws NotFoundHttpException if no user is found with that ID.
     */
    public function editAction(Application $app, Request $request, $id)
    {
        $errors = array();

        $user = $this->userManager->getUser($id);

        if (!$user) {
            throw new NotFoundHttpException('No user was found with that ID.');
        }

        if ($request->isMethod('POST')) {
            $user->setName($request->request->get('userName'));
            $user->setEmail($request->request->get('email'));

            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirmPassword');
            if (!empty($password)) {
                if ($password == $confirmPassword && !empty($confirmPassword)) {
                    $this->userManager->setUserPassword($user, $request->request->get('password'));
                } else {
                    $errors[] = "Passwords do not match.";
                }
            }
            if ($request->request->get('role')) {
                $user->addRole($request->request->get('role'));
            } else {
                $user->removeRole($request->request->get('role'));
            }

            $errors += $this->userManager->validate($user);

            if (empty($errors)) {
                $this->userManager->update($user);
                $msg = 'Saved account information.' . ($request->request->get('password') ? ' Changed password.' : '');
                $app['session']->getFlashBag()->set('success', $msg);
                return $app->redirect($app['url_generator']->generate('user.list'));
            }
        }

        return $app['twig']->render('@user/management/edit.html.twig', array(
            'errors' => $errors,
            'user' => $user,
        ));
    }

    /**
     * List Users Action.
     *
     * @route /console/user
     * @param Application $app
     * @param Request $request
     * @return twig rendered template
     */
    public function listAction(Application $app, Request $request)
    {
        $users = $this->userManager->findBy(array());

        // Unable to modify self from User Management (prevents no admins on system)
        $position = array_search($app['user'], $users);
        unset($users[$position]);

        return $app['twig']->render('@user/management/index.html.twig', array(
            'users' => $users,
        ));
    }

    /**
     * Enable/Disable User Action
     *
     * @route /console/user/{id}/enable
     * @param Application $app
     * @param Request $reqeust
     * @param int $id
     * @return JSON 0 for disabled or 1 for enabled
     */
    public function enableAction(Application $app, Request $request, $id)
    {
        if ($request->isMethod('POST')) {
            $user = $this->userManager->getUser($id);

            if (!$user) {
                throw new NotFoundHttpException('No user was found with that ID.');
            }
            
            if ($user->getEnabled()) {
                $user->setEnabled(0);
            } else {
                $user->setEnabled(1);
            }

            $this->userManager->update($user);
            return $app->json($user->getEnabled());
        } else {
            return new AccessDeniedException("HTTP method not supported");
        }
    }

    /**
     * I forgot my Password User Action
     *
     * @route /console/iforgot
     * @param Application $app
     * @param Request $request
     * @return twig rendered template
     */
    public function iforgotAction(Application $app, Request $request)
    {
        error_log($request->getMethod());
        if ($request->isMethod('POST')) {
            $new_pw = $this->generatePassword();
            $user = $this->userManager->findOneBy(array('email' => $request->get('email')));
            if($user){   
                $this->userManager->setUserPassword($user, $new_pw);
                $this->userManager->update($user);
                $msg = "Your temporary password has been sent to your email.";
                $app['session']->getFlashBag()->set('success', $msg);
                error_log(mail($username,'[Antic] Password Reset', "Your temporary password is: $new_pw"));
                error_log($new_pw);
            }
            else{
                $app['session']->getFlashBag()->set('notice', "Email address not found.");
            }
        }

        return $app['twig']->render('@user/iforgot/iforgot.html.twig');
    }
    /**
     * Simple psuedo-random password generator.
     *
     * @param Length of pw to generate $length
     * @return Generated password
     */
    private function generatePassword($length = 8) {
        $chars = 'bcdfghjkmnpqrstvwxyzBCDFGHJKLMNPQRSTVWXYZ23456789';
        $count = mb_strlen($chars);

        for ($i = 0, $result = ''; $i < $length; $i++) {
            $index = rand(0, $count - 1);
            $result .= mb_substr($chars, $index, 1);
        }

        return $result;
    }
}