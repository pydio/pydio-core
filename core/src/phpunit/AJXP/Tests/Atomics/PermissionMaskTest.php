<?php
/**
 * Created by PhpStorm.
 * User: charles
 * Date: 12/09/2015
 * Time: 10:42
 */

namespace AJXP\Tests\Atomics;


class AJXP_PermissionMaskTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param \AJXP_Permission $permission
     */
    protected function isRW($permission){
        $this->assertTrue($permission->canRead());
        $this->assertTrue($permission->canWrite());
        $this->assertFalse($permission->denies());
    }

    /**
     * @param \AJXP_Permission $permission
     */
    protected function isReadonly($permission){
        $this->assertTrue($permission->canRead());
        $this->assertFalse($permission->canWrite());
        $this->assertFalse($permission->denies());
    }

    /**
     * @param \AJXP_Permission $permission
     */
    protected function isWriteonly($permission){

        $this->assertFalse($permission->canRead());
        $this->assertTrue($permission->canWrite());
        $this->assertFalse($permission->denies());

    }

    /**
     * @param \AJXP_Permission $permission
     */
    protected function isDenied($permission){

        $this->assertTrue($permission->denies());
        $this->assertFalse($permission->canRead());
        $this->assertFalse($permission->canWrite());

    }


    public function testSimplePermission(){

        $perm = new \AJXP_Permission();
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

        $readonly = new \AJXP_Permission("r");
        $writeonly = new \AJXP_Permission("w");
        $readwrite = new \AJXP_Permission("rw");
        $deny = new \AJXP_Permission("d");
        $empty = new \AJXP_Permission();

        $this->isRW($writeonly->override($readonly));
        $this->isRW($readonly->override($writeonly));
        $this->isRW($readwrite->override($readonly));
        $this->isRW($readwrite->override($writeonly));
        $this->isRW($readonly->override($readwrite));
        $this->isRW($writeonly->override($readwrite));

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
        $mask = new \AJXP_PermissionMask();
        $mask->updateBranch("/a1/b1/c1", new \AJXP_Permission("r"));
        echo "\n";
        $mask->toStr($mask->getTree(), 1);
        $mask->updateBranch("/a1/b1/c2", new \AJXP_Permission("rw"));
        $mask->updateBranch("/a1/b1/c3", new \AJXP_Permission());
        echo "\n";
        $mask->toStr($mask->getTree(), 1);
        $mask->updateBranch("/a1/b2", new \AJXP_Permission("rw"));
        echo "\n";
        $mask->toStr($mask->getTree(), 1);

        $mask->updateBranch("/a1/b3", new \AJXP_Permission());

        $mask->updateBranch("a2/b1", new \AJXP_Permission());

        $this->assertFalse($mask->match("/", \AJXP_Permission::WRITE));
        $this->assertTrue($mask->match("/", \AJXP_Permission::READ));
        $this->assertTrue($mask->match("/a1/b1/c1", \AJXP_Permission::READ));
        $this->assertTrue($mask->match("/a1/b1", \AJXP_Permission::READ));
        $this->assertFalse($mask->match("/a1/b1", \AJXP_Permission::WRITE));

        $this->assertFalse($mask->match("/a1/b1/c3", \AJXP_Permission::READ));
        $this->assertTrue($mask->match("/a1/b1/c1/d1/e1/f1", \AJXP_Permission::READ));
        $this->assertFalse($mask->match("/a1/b1/c1/d1/e1/f1", \AJXP_Permission::WRITE));

        // This overrides the whole /a1/b1 branch >> do we want that?
        $mask2 = new \AJXP_PermissionMask();
        $mask2->updateBranch("/a1/b1", new \AJXP_Permission("d"));
        //$mask->override($mask2);

        echo "mask 2\n";
        $mask2->toStr($mask2->getTree(), 0);
        echo "mask 1\n";
        $mask->toStr($mask->getTree(), 0);
        echo "Mask 2 override mask 1\n";
        $mask->override($mask2);
        $mask->toStr($mask->getTree(), 0);

        //$this->assertTrue($mask->match("/a1/b1", \AJXP_Permission::WRITE));
        // Todo write more tests

        $mask->updateBranch("/a1/b1/c1", new \AJXP_Permission("w"));

        //$this->assertFalse($mask->match("/a1/b1/c1", \AJXP_Permission::READ));
        $this->assertTrue($mask->match("/a1/b1/c1", \AJXP_Permission::WRITE));

       //
       //
       // $this->assertTrue($mask->match("/a1/b1/c2", \AJXP_Permission::READ));
        //$this->assertTrue($mask->match("/a1/b1/c2", \AJXP_Permission::WRITE));

        //$this->assertTrue($mask->match("/a1", \AJXP_Permission::DENY));



        /**
         * Nice director
         */
        $DirectorNice_PersonalWsp = new \AJXP_PermissionMask();
        $DirectorNice_PersonalWsp->updateBranch("/", new \AJXP_Permission("rw"));
        $DirectorNice_PersonalWsp->updateBranch("/a1", new \AJXP_Permission("rw"));
        $DirectorNice_PersonalWsp->updateBranch("/a1/b1", new \AJXP_Permission("rw"));


        $DirectorNice_ShareService_NonRestreints = new \AJXP_PermissionMask();
        $DirectorNice_ShareService_NonRestreints->updateBranch("/", new \AJXP_Permission("r"));
        $DirectorNice_ShareService_Restreints = new \AJXP_PermissionMask();
        $DirectorNice_ShareService_Restreints->updateBranch("/", new \AJXP_Permission("d"));

        $DirectorNice_agent_Risso = new \AJXP_PermissionMask();
        $DirectorNice_agent_Risso->updateBranch("/Transaction", new \AJXP_Permission("rw"));
        $DirectorNice_agent_Risso->updateBranch("/Gestion", new \AJXP_Permission("rw"));
        $DirectorNice_agent_Risso->updateBranch("/Syndic", new \AJXP_Permission("rw"));
        $DirectorNice_agent_Risso->updateBranch("/Location", new \AJXP_Permission("rw"));
        $DirectorNice_agent_Risso->updateBranch("/General", new \AJXP_Permission("rw"));

        $DirectorNice_Inters_Mailles = new \AJXP_PermissionMask();
        $DirectorNice_Inters_Mailles->updateBranch("/Nice", new \AJXP_Permission("rw"));
        $DirectorNice_Inters_Mailles->updateBranch("/CSPNice", new \AJXP_Permission("r"));
        $DirectorNice_Inters_Mailles->updateBranch("/Cannes", new \AJXP_Permission("r"));
        $DirectorNice_Inters_Mailles->updateBranch("/Menton", new \AJXP_Permission("r"));


        /**
         * Chef of Nice Risso
         */
        $Chef_Nice_Risso = new \AJXP_PermissionMask();
        $Chef_Nice_Risso->updateBranch("/Transaction", new \AJXP_Permission("rw"));
        $Chef_Nice_Risso->updateBranch("/Gestion", new \AJXP_Permission("rw"));
        $Chef_Nice_Risso->updateBranch("/Location", new \AJXP_Permission("rw"));
        $Chef_Nice_Risso->updateBranch("/Syndic", new \AJXP_Permission("rw"));
        $Chef_Nice_Risso->updateBranch("/General", new \AJXP_Permission("rw"));


        $Chef_Nice_Risso_Inters_Mailles = new  \AJXP_PermissionMask();
        $Chef_Nice_Risso_Inters_Mailles->updateBranch("/Nice", new \AJXP_Permission("rw"));
        $Chef_Nice_Risso_Inters_Mailles->updateBranch("/CSPNice", new \AJXP_Permission("d"));
        $Chef_Nice_Risso_Inters_Mailles->updateBranch("/Cannes", new \AJXP_Permission("d"));
        $Chef_Nice_Risso_Inters_Mailles->updateBranch("/Menton", new \AJXP_Permission("d"));


        $Chef_Nice_Risso_ShareService_NonRestreints = new \AJXP_PermissionMask();
        $Chef_Nice_Risso_ShareService_NonRestreints->updateBranch("/", new \AJXP_Permission("rw"));

        $Chef_Nice_Risso_ShareService_Restreints = new \AJXP_PermissionMask();
        $Chef_Nice_Risso_ShareService_Restreints->updateBranch("/", new \AJXP_Permission("d"));



        $Chef_Nice_Cessole = new \AJXP_PermissionMask();
        $Chef_Nice_Cessole->updateBranch("/Transaction", new \AJXP_Permission("rw"));
        $Chef_Nice_Cessole->updateBranch("/Gestion", new \AJXP_Permission("rw"));
        $Chef_Nice_Cessole->updateBranch("/Location", new \AJXP_Permission("rw"));
        $Chef_Nice_Cessole->updateBranch("/Syndic", new \AJXP_Permission("rw"));
        $Chef_Nice_Cessole->updateBranch("/General", new \AJXP_Permission("rw"));

        $Chef_Nice_Ferber = new \AJXP_PermissionMask();
        $Chef_Nice_Ferber->updateBranch("/Transaction", new \AJXP_Permission("rw"));
        $Chef_Nice_Ferber->updateBranch("/Gestion", new \AJXP_Permission("rw"));
        $Chef_Nice_Ferber->updateBranch("/Location", new \AJXP_Permission("rw"));
        $Chef_Nice_Ferber->updateBranch("/Syndic", new \AJXP_Permission("rw"));
        $Chef_Nice_Ferber->updateBranch("/General", new \AJXP_Permission("rw"));

        $Chef_Nice_Massena = new \AJXP_PermissionMask();
        $Chef_Nice_Massena->updateBranch("/Transaction", new \AJXP_Permission("rw"));
        $Chef_Nice_Massena->updateBranch("/Gestion", new \AJXP_Permission("rw"));
        $Chef_Nice_Massena->updateBranch("/Location", new \AJXP_Permission("rw"));
        $Chef_Nice_Massena->updateBranch("/Syndic", new \AJXP_Permission("rw"));
        $Chef_Nice_Massena->updateBranch("/General", new \AJXP_Permission("rw"));


        // Mask for Share Mailles
        $MaskShareMailles = new \AJXP_PermissionMask();
        $MaskShareMailles->updateBranch("/Nice", new \AJXP_Permission("rw"));
        $MaskShareMailles->updateBranch("/CSPNice", new \AJXP_Permission("rw"));
        $MaskShareMailles->updateBranch("/Cannes", new \AJXP_Permission("rw"));
        $MaskShareMailles->updateBranch("/Raphael", new \AJXP_Permission("rw"));
        $MaskShareMailles->updateBranch("/Maximin", new \AJXP_Permission("rw"));
        $MaskShareMailles->updateBranch("/Menton", new \AJXP_Permission("rw"));
        $MaskShareMailles->updateBranch("/Manosque", new \AJXP_Permission("rw"));
        $MaskShareMailles->updateBranch("/DADS", new \AJXP_Permission("rw"));


        // share services
        $MaskShareDGNonR = new \AJXP_PermissionMask();
        $MaskShareDGNonR->updateBranch("/", new \AJXP_Permission("rw"));

        $MaskShareDGR = new \AJXP_PermissionMask();
        $MaskShareDGR->updateBranch("/", new \AJXP_Permission("rw"));

        $MaskShareComptabNonR = new \AJXP_PermissionMask();
        $MaskShareComptabNonR->updateBranch("/", new \AJXP_Permission("rw"));

        $MaskShareComptabR = new \AJXP_PermissionMask();
        $MaskShareComptabR->updateBranch("/", new \AJXP_Permission("rw"));

        $MaskShareConforNonR = new \AJXP_PermissionMask();
        $MaskShareConforNonR->updateBranch("/", new \AJXP_Permission("rw"));

        $MaskShareConforR = new \AJXP_PermissionMask();
        $MaskShareConforR->updateBranch("/", new \AJXP_Permission("rw"));

        $MaskShareHRNonR = new \AJXP_PermissionMask();
        $MaskShareHRNonR->updateBranch("/", new \AJXP_Permission("rw"));

        $MaskShareHRR = new \AJXP_PermissionMask();
        $MaskShareHRR->updateBranch("/", new \AJXP_Permission("rw"));

        $MaskShareMarketNonR = new \AJXP_PermissionMask();
        $MaskShareMarketNonR->updateBranch("/", new \AJXP_Permission("rw"));

        $MaskShareMarketR = new \AJXP_PermissionMask();
        $MaskShareMarketR->updateBranch("/", new \AJXP_Permission("rw"));

/////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////


        /**
         * Director Nice
         */

        /**
         * test permission on personal workspace
        */
        $this->assertTrue($DirectorNice_PersonalWsp->match("/a1/b1", \AJXP_Permission::WRITE));
        $this->assertTrue($DirectorNice_PersonalWsp->match("/a1/c1", \AJXP_Permission::WRITE));
        $this->assertTrue($DirectorNice_PersonalWsp->match("/", \AJXP_Permission::WRITE));
        $this->assertTrue($DirectorNice_PersonalWsp->match("/a1/b1", \AJXP_Permission::READ));
        $this->assertTrue($DirectorNice_PersonalWsp->match("/a1/c1", \AJXP_Permission::READ));
        $this->assertTrue($DirectorNice_PersonalWsp->match("/", \AJXP_Permission::READ));
        $this->assertFalse($DirectorNice_PersonalWsp->match("/a1/b1/d1/e1", \AJXP_Permission::DENY));
        $this->assertFalse($DirectorNice_PersonalWsp->match("/a1/c1/e2", \AJXP_Permission::DENY));


        /**
         * on workspace of agent Nice Risso
         */
        $this->assertTrue($DirectorNice_agent_Risso->match("/Transaction", \AJXP_Permission::WRITE));
        $this->assertTrue($DirectorNice_agent_Risso->match("/Gestion", \AJXP_Permission::WRITE));
        $this->assertTrue($DirectorNice_agent_Risso->match("/Location", \AJXP_Permission::WRITE));
        $this->assertTrue($DirectorNice_agent_Risso->match("/Location/test", \AJXP_Permission::WRITE));
        $this->assertTrue($DirectorNice_agent_Risso->match("/Syndic", \AJXP_Permission::WRITE));
        $this->assertTrue($DirectorNice_agent_Risso->match("/General", \AJXP_Permission::WRITE));
        // no permssion on other sub folders
        $this->assertFalse($DirectorNice_agent_Risso->match("/Others", \AJXP_Permission::DENY));
        $this->assertFalse($DirectorNice_agent_Risso->match("/Others", \AJXP_Permission::WRITE));
        $this->assertFalse($DirectorNice_agent_Risso->match("/Others", \AJXP_Permission::READ));

        /**
         * On workspace inters- mailles
         */

        $this->assertTrue($DirectorNice_Inters_Mailles->match("/Nice", \AJXP_Permission::WRITE));
        $this->assertFalse($DirectorNice_Inters_Mailles->match("/CSPNice", \AJXP_Permission::WRITE));
        $this->assertFalse($DirectorNice_Inters_Mailles->match("/Cannes", \AJXP_Permission::WRITE));
        $this->assertFalse($DirectorNice_Inters_Mailles->match("/Menton", \AJXP_Permission::WRITE));
        $this->assertTrue($DirectorNice_Inters_Mailles->match("/CSPNice", \AJXP_Permission::READ));
        $this->assertTrue($DirectorNice_Inters_Mailles->match("/Cannes", \AJXP_Permission::READ));
        $this->assertTrue($DirectorNice_Inters_Mailles->match("/Menton", \AJXP_Permission::READ));

        /**
         * On service partage Non restreints
         */

        $this->assertTrue($DirectorNice_ShareService_NonRestreints->match("/test", \AJXP_Permission::READ));
        $this->assertFalse($DirectorNice_ShareService_NonRestreints->match("/test", \AJXP_Permission::WRITE));
        $this->assertFalse($DirectorNice_ShareService_NonRestreints->match("/test/test/test/test/abc/NonRestreints/test", \AJXP_Permission::WRITE));
        /**
         * On service partage Restreints
         */

        $this->assertTrue($DirectorNice_ShareService_NonRestreints->match("/test", \AJXP_Permission::READ));
        $this->assertFalse($DirectorNice_ShareService_NonRestreints->match("/test", \AJXP_Permission::WRITE));
        $this->assertFalse($DirectorNice_ShareService_NonRestreints->match("/test/test/test/test/abc/NonRestreints/stest", \AJXP_Permission::WRITE));

        /**
         * workspace of agent Nice Risso
         */

        /**
         * hef Nice Risso read/write/deny
         */
        $this->assertTrue($Chef_Nice_Risso->match("/Transaction", \AJXP_Permission::WRITE));
        $this->assertTrue($Chef_Nice_Risso->match("/Gestion", \AJXP_Permission::WRITE));
        $this->assertTrue($Chef_Nice_Risso->match("/Location", \AJXP_Permission::WRITE));
        $this->assertTrue($Chef_Nice_Risso->match("/Syndic", \AJXP_Permission::WRITE));
        $this->assertTrue($Chef_Nice_Risso->match("/General", \AJXP_Permission::WRITE));
        $this->assertTrue($Chef_Nice_Risso->match("/Transaction/test", \AJXP_Permission::WRITE));
        $this->assertTrue($Chef_Nice_Risso->match("/Gestion/test", \AJXP_Permission::WRITE));
        $this->assertTrue($Chef_Nice_Risso->match("/Location/test", \AJXP_Permission::WRITE));
        $this->assertTrue($Chef_Nice_Risso->match("/Syndic/test", \AJXP_Permission::WRITE));
        $this->assertTrue($Chef_Nice_Risso->match("/General/test", \AJXP_Permission::WRITE));
        $this->assertTrue($Chef_Nice_Risso->match("/Transaction/test", \AJXP_Permission::READ));
        $this->assertTrue($Chef_Nice_Risso->match("/Gestion/test", \AJXP_Permission::READ));
        $this->assertTrue($Chef_Nice_Risso->match("/Location/test", \AJXP_Permission::READ));
        $this->assertTrue($Chef_Nice_Risso->match("/Syndic/test", \AJXP_Permission::READ));
        $this->assertTrue($Chef_Nice_Risso->match("/General/test", \AJXP_Permission::READ));

        $this->assertFalse($Chef_Nice_Risso->match("/Transaction", \AJXP_Permission::DENY));
        $this->assertFalse($Chef_Nice_Risso->match("/Gestion", \AJXP_Permission::DENY));
        $this->assertFalse($Chef_Nice_Risso->match("/Location", \AJXP_Permission::DENY));
        $this->assertFalse($Chef_Nice_Risso->match("/Syndic", \AJXP_Permission::DENY));
        $this->assertFalse($Chef_Nice_Risso->match("/General", \AJXP_Permission::DENY));
        $this->assertFalse($Chef_Nice_Risso->match("/Transaction/test", \AJXP_Permission::DENY));
        $this->assertFalse($Chef_Nice_Risso->match("/Gestion/test", \AJXP_Permission::DENY));
        $this->assertFalse($Chef_Nice_Risso->match("/Location/test", \AJXP_Permission::DENY));
        $this->assertFalse($Chef_Nice_Risso->match("/Syndic/test", \AJXP_Permission::DENY));
        $this->assertFalse($Chef_Nice_Risso->match("/General/test", \AJXP_Permission::DENY));

        $this->assertTrue($Chef_Nice_Risso_Inters_Mailles->match("/Nice", \AJXP_Permission::WRITE));
        $this->assertTrue($Chef_Nice_Risso_Inters_Mailles->match("/Nice/test/Nice/Nice/Nice/", \AJXP_Permission::WRITE));
        $this->assertFalse($Chef_Nice_Risso_Inters_Mailles->match("/CSPNice", \AJXP_Permission::WRITE));
        $this->assertFalse($Chef_Nice_Risso_Inters_Mailles->match("/Cannes", \AJXP_Permission::WRITE));
        $this->assertFalse($Chef_Nice_Risso_Inters_Mailles->match("/Menton", \AJXP_Permission::WRITE));

    }

    public function testGroupPathPermission(){

        $Fin = new \AJXP_PermissionMask();
        $Fin->updateBranch("/Fin", new \AJXP_Permission("rw"));
        $Fin->updateBranch("/Public", new \AJXP_Permission("rw"));

        $FinInvoices = new \AJXP_PermissionMask();
        $FinInvoices->updateBranch("/Fin/Invoices", new \AJXP_Permission("rw"));

        $FinReports = new \AJXP_PermissionMask();
        $FinReports->updateBranch("/Fin/Reports", new \AJXP_Permission("rw"));

        $FinDocs = new \AJXP_PermissionMask();
        $FinDocs->updateBranch("/Fin/Docs", new \AJXP_Permission("rw"));

        $FinSalary = new \AJXP_PermissionMask();
        $FinSalary->updateBranch("/Fin/Salary", new \AJXP_Permission("rw"));

        $Service = new \AJXP_PermissionMask();
        $Service->updateBranch("/Services", new \AJXP_Permission("rw"));

        $ServiceIT = new \AJXP_PermissionMask();
        $ServiceIT->updateBranch("/Services/IT", new \AJXP_Permission("rw"));
        $ServiceITReports = new \AJXP_PermissionMask();
        $ServiceITReports->updateBranch("/Services/IT/Reports", new \AJXP_Permission("rw"));
        $ServiceITSofts = new \AJXP_PermissionMask();
        $ServiceITSofts->updateBranch("/Services/IT/Softs", new \AJXP_Permission("rw"));
        $ServiceITShares = new \AJXP_PermissionMask();
        $ServiceITShares->updateBranch("/Services/IT/Shares", new \AJXP_Permission("rw"));

        $ServiceHR = new \AJXP_PermissionMask();
        $ServiceHR->updateBranch("/Services/HR", new \AJXP_Permission("rw"));

        $ServiceAdmin = new \AJXP_PermissionMask();
        $ServiceAdmin->updateBranch("/Services/Admin", new \AJXP_Permission("rw"));
        $ServiceAdminRequests = new \AJXP_PermissionMask();
        $ServiceAdminRequests->updateBranch("/Services/Admin/Requests", new \AJXP_Permission("rw"));
        $ServiceAdminTickets = new \AJXP_PermissionMask();
        $ServiceAdminTickets->updateBranch("/Services/Admin/Tickets", new \AJXP_Permission("rw"));

        $ServiceMarketing = new \AJXP_PermissionMask();
        $ServiceMarketing->updateBranch("/Services/Marketing", new \AJXP_Permission("rw"));
        $ServiceMarketingContracts = new \AJXP_PermissionMask();
        $ServiceMarketingContracts->updateBranch("/Services/Marketing/Contracts", new \AJXP_Permission("rw"));
        $ServiceMarketingReports = new \AJXP_PermissionMask();
        $ServiceMarketingReports->updateBranch("/Services/Marketing/Reports", new \AJXP_Permission("rw"));
        $ServiceMarketingTransmissions = new \AJXP_PermissionMask();
        $ServiceMarketingTransmissions->updateBranch("/Services/Marketing/Transmission", new \AJXP_Permission("rw"));

        $public = new \AJXP_PermissionMask();
        $public->updateBranch("/Public", new \AJXP_Permission("rw"));

////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////

        $Chef_Fin = new \AJXP_PermissionMask();
        $Chef_Fin->copyMask($Fin);
        $this->assertTrue($Chef_Fin->match("/Fin", \AJXP_Permission::WRITE));
        $this->assertTrue($Chef_Fin->match("/Fin/Invoices", \AJXP_Permission::WRITE));
        $this->assertTrue($Chef_Fin->match("/Fin/Reports", \AJXP_Permission::WRITE));
        $this->assertTrue($Chef_Fin->match("/Fin/Docs", \AJXP_Permission::WRITE));
        $this->assertTrue($Chef_Fin->match("/Fin/Salary", \AJXP_Permission::WRITE));
        $this->assertTrue($Chef_Fin->match("/Fin/Salary/test", \AJXP_Permission::WRITE));
        $this->assertFalse($Chef_Fin->match("/Services", \AJXP_Permission::WRITE));
        $this->assertFalse($Chef_Fin->match("/abc", \AJXP_Permission::WRITE));

        $UserInvoice = new \AJXP_PermissionMask();
        $UserInvoice->copyMask($FinInvoices);

        $this->assertTrue($FinInvoices->match("/Fin/Invoices", \AJXP_Permission::WRITE));
        $this->assertFalse($FinInvoices->match("/Fin/Reports", \AJXP_Permission::DENY));
        $this->assertFalse($FinInvoices->match("/Fin/Reports", \AJXP_Permission::READ));
        $this->assertFalse($FinInvoices->match("/Fin/Reports", \AJXP_Permission::WRITE));
        echo "==================\n";
        echo "Fin 0\n";
        $Fin->toStr($Fin->getTree(), 1);
        echo "UserInvoice 0\n";
        $UserInvoice->toStr($UserInvoice->getTree(), 1);
        $UserInvoice = $FinInvoices->override($UserInvoice);
        echo "UserInvoice 1\n";
        $UserInvoice->toStr($UserInvoice->getTree(), 1);
        $UserInvoice = $Fin->override($UserInvoice);
        echo "UserInvoice 2\n";
        $UserInvoice->toStr($UserInvoice->getTree(), 1);

        $this->assertTrue($UserInvoice->match("/Public", \AJXP_Permission::WRITE));
        $this->assertTrue($UserInvoice->match("/Public", \AJXP_Permission::READ));
        $this->assertFalse($UserInvoice->match("/Public", \AJXP_Permission::DENY));

        $UserInvoice->updateBranch("/Public", new \AJXP_Permission("d"));
        echo "UserInvoice 3\n";
        $UserInvoice->toStr($UserInvoice->getTree(), 1);

        $this->assertFalse($UserInvoice->match("/Public", \AJXP_Permission::WRITE));
        $this->assertFalse($UserInvoice->match("/Public", \AJXP_Permission::READ));
        $this->assertTrue($UserInvoice->match("/Public", \AJXP_Permission::DENY));

        $this->assertTrue($UserInvoice->match("/Fin/Invoices", \AJXP_Permission::WRITE));
        $this->assertFalse($UserInvoice->match("/Fin/Reports", \AJXP_Permission::DENY));
        $this->assertFalse($UserInvoice->match("/Fin/Reports", \AJXP_Permission::READ));
        $this->assertFalse($UserInvoice->match("/Fin/Reports", \AJXP_Permission::WRITE));

        $GeneralManager = new \AJXP_PermissionMask();
        $GeneralManager->updateBranch("/", new \AJXP_Permission(3));

        $this->assertTrue($GeneralManager->match("/Fin/Invoices", \AJXP_Permission::WRITE));
        $this->assertTrue($GeneralManager->match("/Fin/Invoices", \AJXP_Permission::WRITE));
        $this->assertTrue($GeneralManager->match("/Services/IT", \AJXP_Permission::WRITE));
        $this->assertTrue($GeneralManager->match("/Services/HR", \AJXP_Permission::WRITE));
        $this->assertTrue($GeneralManager->match("/Services/Marketing", \AJXP_Permission::WRITE));
        $this->assertTrue($GeneralManager->match("/ABC", \AJXP_Permission::WRITE));
        $this->assertTrue($GeneralManager->match("/ABC/DEF", \AJXP_Permission::WRITE));


        $FinPublicRole = new \AJXP_PermissionMask();
        $FinPublicRole->updateBranch("/Fin/Docs", new \AJXP_Permission("rw"));

        $ServiceAdminRequestsUser = new \AJXP_PermissionMask();
        $ServiceAdminRequestsUser->copyMask($ServiceAdminRequests);
        $this->assertTrue($ServiceAdminRequestsUser->match("/Services/Admin/Requests", \AJXP_Permission::WRITE));
        $this->assertTrue($ServiceAdminRequestsUser->match("/Services/Admin/Requests", \AJXP_Permission::READ));
        $this->assertFalse($ServiceAdminRequestsUser->match("/Services/Admin/Requests", \AJXP_Permission::DENY));
        $this->assertFalse($ServiceAdminRequestsUser->match("/Services/IT/Reports", \AJXP_Permission::WRITE));
        $this->assertFalse($ServiceAdminRequestsUser->match("/Services/IT/Reports", \AJXP_Permission::READ));
        $this->assertFalse($ServiceAdminRequestsUser->match("/Services/HR", \AJXP_Permission::WRITE));
        $this->assertFalse($ServiceAdminRequestsUser->match("/Services/Marketing/Contracts", \AJXP_Permission::DENY));
        $this->assertFalse($ServiceAdminRequestsUser->match("/Services/Marketing/Contracts", \AJXP_Permission::WRITE));
        $this->assertFalse($ServiceAdminRequestsUser->match("/Services/Marketing/Contracts", \AJXP_Permission::READ));
        $this->assertFalse($ServiceAdminRequestsUser->match("/Fin/Docs", \AJXP_Permission::WRITE));
        $this->assertFalse($ServiceAdminRequestsUser->match("/Public", \AJXP_Permission::WRITE));
        $this->assertFalse($ServiceAdminRequestsUser->match("/Public/write", \AJXP_Permission::WRITE));

        $randomRole = new \AJXP_PermissionMask();
        $randomRole->updateBranch("/Services/Admin/Requests/Secrets", new \AJXP_Permission("d"));

        $randomRole1 = new \AJXP_PermissionMask();
        $randomRole1->updateBranch("/Services/Admin/Requests", new \AJXP_Permission("wr"));

        $randomRole2 = new \AJXP_PermissionMask();
        $randomRole2->updateBranch("/Services/Admin/Requests/Secrets", new \AJXP_Permission("r"));

        echo "================================\n";
        echo "Service Admin Request User 0: \n";
        $ServiceAdminRequestsUser->toStr($ServiceAdminRequestsUser->getTree(), 1);

        $ServiceAdminRequestsUser = $ServiceAdminRequests->override($ServiceAdminRequestsUser);

        echo "Service Admin Request User 1: \n";
        $ServiceAdminRequestsUser->toStr($ServiceAdminRequestsUser->getTree(), 1);

        $ServiceAdminRequestsUser = $ServiceAdmin->override($ServiceAdminRequestsUser);
        echo "Service Admin Request User 2: \n";
        $ServiceAdminRequestsUser->toStr($ServiceAdminRequestsUser->getTree(), 1);

        $ServiceAdminRequestsUser = $Service->override($ServiceAdminRequestsUser);
        echo "Service Admin Request User 3: \n";
        $ServiceAdminRequestsUser->toStr($ServiceAdminRequestsUser->getTree(), 1);

        $ServiceAdminRequestsUser = $public->override($ServiceAdminRequestsUser);

        echo "Service Admin Request User 5: \n";
        $ServiceAdminRequestsUser->toStr($ServiceAdminRequestsUser->getTree(), 1);

        $this->assertTrue($ServiceAdminRequestsUser->match("/Public/write", \AJXP_Permission::WRITE));
        $this->assertTrue($ServiceAdminRequestsUser->match("/Services/Admin/Requests/Secrets", \AJXP_Permission::WRITE));

        $ServiceAdminRequestsUser = $ServiceAdminRequestsUser->override($randomRole);

        $this->assertTrue($ServiceAdminRequestsUser->match("/Public/write", \AJXP_Permission::WRITE));
        $this->assertFalse($ServiceAdminRequestsUser->match("/Services/Admin/Requests/Secrets", \AJXP_Permission::WRITE));
        $this->assertTrue($ServiceAdminRequestsUser->match("/Services/Admin/Requests/Secrets", \AJXP_Permission::DENY));

        echo "Service Admin Request User 6: \n";
        $ServiceAdminRequestsUser->toStr($ServiceAdminRequestsUser->getTree(), 1);

        $ServiceAdminRequestsUser = $ServiceAdminRequestsUser->override($randomRole2);
        echo "Service Admin Request User 7: \n";
        $ServiceAdminRequestsUser->toStr($ServiceAdminRequestsUser->getTree(), 1);

        $this->assertTrue($ServiceAdminRequestsUser->match("/Services/Admin/Requests/Secrets", \AJXP_Permission::READ));
        $this->assertFalse($ServiceAdminRequestsUser->match("/Services/Admin/Requests/Secrets", \AJXP_Permission::WRITE));
        $this->assertFalse($ServiceAdminRequestsUser->match("/Services/Admin/Requests/Secrets", \AJXP_Permission::DENY));

        $ServiceAdminRequestsUser = $ServiceAdminRequestsUser->override($randomRole1);
        echo "Service Admin Request User 8: \n";
        $ServiceAdminRequestsUser->toStr($ServiceAdminRequestsUser->getTree(), 1);


        $this->assertTrue($ServiceAdminRequestsUser->match("/Services/Admin/Requests/Secrets", \AJXP_Permission::READ));
        $this->assertTrue($ServiceAdminRequestsUser->match("/Services/Admin/Requests/Secrets", \AJXP_Permission::WRITE));
        $this->assertFalse($ServiceAdminRequestsUser->match("/Services/Admin/Requests/Secrets", \AJXP_Permission::DENY));

    }
}



