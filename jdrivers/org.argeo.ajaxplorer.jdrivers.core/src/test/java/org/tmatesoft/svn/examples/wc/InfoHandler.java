/*
 * ====================================================================
 * Copyright (c) 2004-2007 TMate Software Ltd.  All rights reserved.
 *
 * This software is licensed as described in the file COPYING, which
 * you should have received as part of this distribution.  The terms
 * are also available at http://svnkit.com/license.html
 * If newer versions of this license are posted there, you may use a
 * newer version instead, at your option.
 * ====================================================================
 */
package org.tmatesoft.svn.examples.wc;
 
import org.tmatesoft.svn.core.SVNNodeKind;
import org.tmatesoft.svn.core.wc.ISVNInfoHandler;
import org.tmatesoft.svn.core.wc.SVNInfo;
 
/*
 * An implementation of ISVNInfoHandler that is  used  in  WorkingCopy.java  to 
 * display  info  on  a  working  copy path.  This implementation is passed  to
 * 
 * SVNWCClient.doInfo(File path, SVNRevision revision, boolean recursive, 
 * ISVNInfoHandler handler) 
 * 
 * For each item to be processed doInfo(..) collects information and creates an 
 * SVNInfo which keeps that information. Then  doInfo(..)  calls  implementor's 
 * handler.handleInfo(SVNInfo) where it passes the gathered info.
 */
public class InfoHandler implements ISVNInfoHandler {
    /*
     * This is an implementation  of  ISVNInfoHandler.handleInfo(SVNInfo info).
     * Just prints out information on a Working Copy path in the manner of  the
     * native SVN command line client.
     */
    public void handleInfo(SVNInfo info) {
        System.out.println("-----------------INFO-----------------");
        System.out.println("Local Path: " + info.getFile().getPath());
        System.out.println("URL: " + info.getURL());
        if (info.isRemote() && info.getRepositoryRootURL() != null) {
            System.out.println("Repository Root URL: "
                    + info.getRepositoryRootURL());
        }
        if(info.getRepositoryUUID() != null){
            System.out.println("Repository UUID: " + info.getRepositoryUUID());
        }
        System.out.println("Revision: " + info.getRevision().getNumber());
        System.out.println("Node Kind: " + info.getKind().toString());
        if(!info.isRemote()){
            System.out.println("Schedule: "
                    + (info.getSchedule() != null ? info.getSchedule() : "normal"));
        }
        System.out.println("Last Changed Author: " + info.getAuthor());
        System.out.println("Last Changed Revision: "
                + info.getCommittedRevision().getNumber());
        System.out.println("Last Changed Date: " + info.getCommittedDate());
        if (info.getPropTime() != null) {
            System.out
                    .println("Properties Last Updated: " + info.getPropTime());
        }
        if (info.getKind() == SVNNodeKind.FILE && info.getChecksum() != null) {
            if (info.getTextTime() != null) {
                System.out.println("Text Last Updated: " + info.getTextTime());
            }
            System.out.println("Checksum: " + info.getChecksum());
        }
        if (info.getLock() != null) {
            if (info.getLock().getID() != null) {
                System.out.println("Lock Token: " + info.getLock().getID());
            }
            System.out.println("Lock Owner: " + info.getLock().getOwner());
            System.out.println("Lock Created: "
                    + info.getLock().getCreationDate());
            if (info.getLock().getExpirationDate() != null) {
                System.out.println("Lock Expires: "
                        + info.getLock().getExpirationDate());
            }
            if (info.getLock().getComment() != null) {
                System.out.println("Lock Comment: "
                        + info.getLock().getComment());
            }
        }
    }
}