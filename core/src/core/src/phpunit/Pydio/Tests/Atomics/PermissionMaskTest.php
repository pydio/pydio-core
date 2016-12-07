<?php
/**
 * Created by PhpStorm.
 * User: charles
 * Date: 12/09/2015
 * Time: 10:42
 */

namespace Pydio\Tests\Atomics;


use Pydio\Access\Core\Filter\AJXP_Permission;
use Pydio\Access\Core\Filter\AJXP_PermissionMask;
use Pydio\Access\Core\FilterAJXP_Permission;

class AJXP_PermissionMaskTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param AJXP_Permission $permission
     */
    protected function isRW($permission){
        $this->assertTrue($permission->canRead());
        $this->assertTrue($permission->canWrite());
        $this->assertFalse($permission->denies());
    }

    /**
     * @param AJXP_Permission $permission
     */
    protected function isReadonly($permission){
        $this->assertTrue($permission->canRead());
        $this->assertFalse($permission->canWrite());
        $this->assertFalse($permission->denies());
    }

    /**
     * @param AJXP_Permission $permission
     */
    protected function isWriteonly($permission){

        $this->assertFalse($permission->canRead());
        $this->assertTrue($permission->canWrite());
        $this->assertFalse($permission->denies());

    }

    /**
     * @param AJXP_Permission $permission
     */
    protected function isDenied($permission){

        $this->assertTrue($permission->denies());
        $this->assertFalse($permission->canRead());
        $this->assertFalse($permission->canWrite());

    }


    public function testSimplePermission(){

        $perm = new AJXP_Permission();
        $this->isDenied($perm);

        $perm->setRead();
        $this->isReadonly($perm);
        $perm->setWrite();
        $this->isRW($perm);
        $perm->setDeny();
        $this->isDenied($perm);

        $perm->setDeny(false);

        // When perm is empty, => denied by default
        $this->isDenied($perm);
        $perm->setRead();
        $perm->setRead(false);
        $this->isDenied($perm);
        $perm->setWrite();
        $perm->setWrite(false);
        $this->isDenied($perm);

    }


    public function testPermissionOverride(){

        $readonly = new AJXP_Permission("r");
        $writeonly = new AJXP_Permission("w");
        $readwrite = new AJXP_Permission("rw");
        $deny = new AJXP_Permission("d");
        $empty = new AJXP_Permission();

        $this->isRW($writeonly->override($readonly));
        $this->isWriteonly($writeonly->override($writeonly));
        $this->isWriteonly($writeonly->override($deny));

        $this->isRW($readonly->override($writeonly));
        $this->isRW($readwrite->override($readonly));
        $this->isRW($readwrite->override($writeonly));
        $this->isReadonly($readonly->override($readwrite));
        $this->isWriteonly($writeonly->override($readwrite));

        $this->isDenied($deny->override($readonly));
        $this->isDenied($deny->override($writeonly));
        $this->isDenied($deny->override($readwrite));

        $this->isDenied($empty->override($readonly));
        $this->isDenied($empty->override($writeonly));
        $this->isDenied($empty->override($readwrite));
        $this->isRW($writeonly->override($readonly));
    }

    /**
     * @parra
     *
     */
    public function testPermissionMask(){
        $mask = new AJXP_PermissionMask();
        $mask->updateBranch("/a1/b1/c1", new AJXP_Permission("r"));
        echo "\n";
        $level = 1;
        $mask->toStr($mask->getTree(), $level);
        $mask->updateBranch("/a1/b1/c2", new AJXP_Permission("rw"));
        $mask->updateBranch("/a1/b1/c3", new AJXP_Permission());
        echo "\n";
        $mask->toStr($mask->getTree(), $level);
        $mask->updateBranch("/a1/b2", new AJXP_Permission("rw"));
        echo "\n";
        $mask->toStr($mask->getTree(), $level);

        $mask->updateBranch("/a1/b3", new AJXP_Permission());

        $mask->updateBranch("a2/b1", new AJXP_Permission());

        $this->assertFalse($mask->match("/", AJXP_Permission::WRITE));
        $this->assertTrue($mask->match("/", AJXP_Permission::READ));
        $this->assertTrue($mask->match("/a1/b1/c1", AJXP_Permission::READ));
        $this->assertTrue($mask->match("/a1/b1", AJXP_Permission::READ));
        $this->assertFalse($mask->match("/a1/b1", AJXP_Permission::WRITE));

        $this->assertFalse($mask->match("/a1/b1/c3", AJXP_Permission::READ));
        $this->assertTrue($mask->match("/a1/b1/c1/d1/e1/f1", AJXP_Permission::READ));
        $this->assertFalse($mask->match("/a1/b1/c1/d1/e1/f1", AJXP_Permission::WRITE));

        // This overrides the whole /a1/b1 branch >> do we want that?
        $mask2 = new AJXP_PermissionMask();
        $mask2->updateBranch("/a1/b1", new AJXP_Permission("d"));
        //$mask->override($mask2);

        echo "mask 2\n";
        $level = 0;
        $mask2->toStr($mask2->getTree(), $level);
        echo "mask 1\n";
        $mask->toStr($mask->getTree(), $level);
        echo "Mask 2 override mask 1\n";
        $mask->override($mask2);
        $mask->toStr($mask->getTree(), $level);

        //$this->assertTrue($mask->match("/a1/b1", AJXP_Permission::WRITE));
        // Todo write more tests

        $mask->updateBranch("/a1/b1/c1", new AJXP_Permission("w"));

        //$this->assertFalse($mask->match("/a1/b1/c1", AJXP_Permission::READ));
        $this->assertTrue($mask->match("/a1/b1/c1", AJXP_Permission::WRITE));

       //
       //
       // $this->assertTrue($mask->match("/a1/b1/c2", AJXP_Permission::READ));
        //$this->assertTrue($mask->match("/a1/b1/c2", AJXP_Permission::WRITE));

        //$this->assertTrue($mask->match("/a1", AJXP_Permission::DENY));

        // Test that a deny is cutting the sub branches
        $mask1 = new AJXP_PermissionMask();
        $mask1->updateBranch("/a1/b1", new AJXP_Permission("rw"));
        $mask1->updateBranch("/a1/b2", new AJXP_Permission("rw"));
        $mask1->updateBranch("/a1/b3/c1", new AJXP_Permission("rw"));
        $mask1->updateBranch("/a1/b3/c2", new AJXP_Permission("rw"));

        $mask2 = new AJXP_PermissionMask();
        $mask2->updateBranch("/a1", new AJXP_Permission("d"));

        $result = $mask1->override($mask2);
        $this->assertFalse($result->match("/a1", AJXP_Permission::READ));
        $this->assertFalse($result->match("/a1/b2", AJXP_Permission::READ));
        $this->assertFalse($result->match("/a1/b3", AJXP_Permission::READ));
        $this->assertFalse($result->match("/a1/b3/c1", AJXP_Permission::READ));
        $this->assertFalse($result->match("/a1/any", AJXP_Permission::READ));

    }

}



