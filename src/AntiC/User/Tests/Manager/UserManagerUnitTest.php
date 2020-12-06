<?php

namespace AntiC\User\Tests\Manager;

use AntiC\User\Manager\UserManager;

/**
 * Unit Tests User Manager
 *
 * @author Jeremy Smereka <jeremy.smereka@ekoed.com>
 */
class UserManagerUnitTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Creates Application to use
     *
     * @return Application
     */
    public function createApplication()
    {
        return require __DIR__.'/../../../../../app/bootstrap.php';
    }

    public function createUserManager()
    {
        $app = $this->createApplication();

        return new UserManager($app['db'], $app);
    }

    /**
     * Tests loading of User by Username (Email)
     */
    public function testLoadUserByUsername()
    {
        $emailAddress = "test@test.test";

        $userManager = $this->createUserManager();
        $user = $userManager->loadUserByUsername($emailAddress);
        $this->assertEquals("test", $user->getName());

        return $user;
    }

    /**
     * Tests Refresh User
     */
    public function testRefreshUser()
    {
        $emailAddress = "test@test.test";

        $userManager = $this->createUserManager();
        $user = $userManager->loadUserByUsername($emailAddress);
        $refreshedUser = $userManager->refreshUser($user);

        $this->assertEquals($user->getName(), $refreshedUser->getName());

        return $user;
    }

    /**
     * Tests that Encoding
     */
    public function testEncodeUserPassword()
    {
        $emailAddress = "test@test.test";
        $accPassword = "test";

        $userManager = $this->createUserManager();
        $user = $userManager->loadUserByUsername($emailAddress);
        $encodedPassword = $userManager->encodeUserPassword($user, $accPassword);
        $this->assertTrue(password_verify($accPassword, $encodedPassword));
    }   

    /**
     * Tests the Find One By Method
     */
    public function testFindOneBy()
    {
        $name = "test";

        $userManager = $this->createUserManager();
        $user = $userManager->findOneBy(array("name" => $name));
        $this->assertNotEmpty($user);
        $this->assertEquals($name, $user->getName());
    }

    /**
     * Test Create User
     */
    public function testCreateUser()
    {
        $emailAddress = "test@test2.test";
        $accPassword = "test";
        $name = "test2";
        $role = array("ROLE_USER");

        $userManager = $this->createUserManager();
        $user = $userManager->createUser($emailAddress, $accPassword, $name, $role);
        $this->assertNotEmpty($user);
        $this->assertEquals($emailAddress, $user->getEmail());
        $this->assertEquals($name, $user->getName());
        $this->assertTrue($user->hasRole($role[0]));

        return $user;
    }

    /**
     * Tests the Insert Functionality
     *
     * @depends testCreateUser
     */
    public function testInsert($user)
    {
        $userManager = $this->createUserManager();
        $userManager->insert($user);
        $dbUser = $userManager->loadUserByUsername($user->getEmail());
        $this->assertEquals($user->getEmail(), $dbUser->getEmail());
        $this->assertEquals($user->getName(), $dbUser->getName());
        $this->assertEquals($user->getRoles(), $dbUser->getRoles());
        $this->assertEquals($user->getPassword(), $dbUser->getPassword());

        return $user;
    }

    /**
     * Tests the Update Functionality
     * 
     * @depends testInsert
     */
    public function testUpdate($user)
    {
        $userManager = $this->createUserManager();
        $user->setName("HALFONZ");
        $userManager->update($user);
        $dbUser = $userManager->loadUserByUsername($user->getEmail());
        $this->assertEquals($user->getName(), $dbUser->getName());

        return $user;
    }

    /**
     * Tests the Delete Functionality
     *
     * @depends testUpdate
     * @expectedException Symfony\Component\Security\Core\Exception\UsernameNotFoundException
     */
    public function testDelete($user)
    {
        $userManager = $this->createUserManager();
        $userManager->delete($user);

        $userManager->loadUserByUsername($user->getEmail());
    }



}