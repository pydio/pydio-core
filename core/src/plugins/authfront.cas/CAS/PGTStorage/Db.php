<?php
/*
 * Copyright © 2003-2010, The ESUP-Portail consortium & the JA-SIG Collaborative.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *     * Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright notice,
 *       this list of conditions and the following disclaimer in the documentation
 *       and/or other materials provided with the distribution.
 *     * Neither the name of the ESUP-Portail consortium & the JA-SIG
 *       Collaborative nor the names of its contributors may be used to endorse or
 *       promote products derived from this software without specific prior
 *       written permission.

 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
/**
 * @file CAS/PGTStorage/Db.php
 * Basic class for PGT database storage
 */

/**
 * @class CAS_PGTStorage_Db
 * The CAS_PGTStorage_Db class is a class for PGT database storage.
 *
 * @author Daniel Frett <daniel.frett at gmail.com>
 *
 * @ingroup internalPGTStorageDb
 */

define('CAS_PGT_STORAGE_DB_DEFAULT_TABLE', 'cas_pgts');

class CAS_PGTStorage_Db extends CAS_PGTStorage_AbstractStorage
{
	/**
	 * @addtogroup internalCAS_PGTStorageDb
	 * @{
	 */

	/**
	 * the PDO object to use for database interactions
	 */
	private $_pdo;

	/**
	 * This method returns the PDO object to use for database interactions.
	 *
	 * @return the PDO object
	 */
	private function getPdo()
	{
		return $this->_pdo;
	}

	/**
	 * database connection options to use when creating a new PDO object
	 */
	private $_dsn;
	private $_username;
	private $_password;
	private $_table_options;

	/**
	 * the table to use for storing/retrieving pgt's
	 */
	private $_table;

	/**
	 * This method returns the table to use when storing/retrieving PGT's
	 *
	 * @return the name of the pgt storage table.
	 */
	private function getTable()
	{
		return $this->_table;
	}

	// ########################################################################
	//  DEBUGGING
	// ########################################################################

	/**
	 * This method returns an informational string giving the type of storage
	 * used by the object (used for debugging purposes).
	 *
	 * @return an informational string.
	 */
	public function getStorageType()
	{
		return "db";
	}

	/**
	 * This method returns an informational string giving informations on the
	 * parameters of the storage.(used for debugging purposes).
	 *
	 * @return an informational string.
	 * @public
	 */
	public function getStorageInfo()
	{
		return 'table=`'.$this->getTable().'\'';
	}

	// ########################################################################
	//  CONSTRUCTOR
	// ########################################################################

	/**
	 * The class constructor.
	 *
	 * @param $cas_parent the CAS_Client instance that creates the object.
	 * @param $dsn_or_pdo a dsn string to use for creating a PDO object or a PDO object
	 * @param $username the username to use when connecting to the database
	 * @param $password the password to use when connecting to the database
	 * @param $table the table to use for storing and retrieving PGT's
	 * @param $driver_options any driver options to use when connecting to the database
	 */
	public function __construct($cas_parent, $dsn_or_pdo, $username='', $password='', $table='', $driver_options=null)
	{
		phpCAS::traceBegin();
		// call the ancestor's constructor
		parent::__construct($cas_parent);

		// set default values
		if ( empty($table) ) $table = CAS_PGT_STORAGE_DB_DEFAULT_TABLE;
		if ( !is_array($driver_options) ) $driver_options = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);

		// store the specified parameters
		if($dsn_or_pdo instanceof PDO) {
			$this->_pdo = $dsn_or_pdo;
		}
		else {
			$this->_dsn = $dsn_or_pdo;
			$this->_username = $username;
			$this->_password = $password;
			$this->_driver_options = $driver_options;
		}

		// store the table name
		$this->_table = $table;

		phpCAS::traceEnd();
	}

	// ########################################################################
	//  INITIALIZATION
	// ########################################################################

	/**
	 * This method is used to initialize the storage. Halts on error.
	 */
	public function init()
	{
		phpCAS::traceBegin();
		// if the storage has already been initialized, return immediatly
		if ( $this->isInitialized() )
		return;

		// initialize the base object
		parent::init();

		// create the PDO object if it doesn't exist already
		if(!($this->_pdo instanceof PDO)) {
			try {
				$this->_pdo = new PDO($this->_dsn, $this->_username, $this->_password, $this->_driver_options);
			}
			catch(PDOException $e) {
				phpCAS::error('Database connection error: ' . $e->getMessage());
			}
		}
		
		phpCAS::traceEnd();
	}

	// ########################################################################
	//  PDO database interaction
	// ########################################################################

	/**
	 * attribute that stores the previous error mode for the PDO handle while processing a transaction
	 */
	private $_errMode;

	/**
	 * This method will enable the Exception error mode on the PDO object
	 */
	private function setErrorMode()
	{
		// get PDO object and enable exception error mode
		$pdo = $this->getPdo();
		$this->_errMode = $pdo->getAttribute(PDO::ATTR_ERRMODE);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}

	/**
	 * this method will reset the error mode on the PDO object
	 */
	private function resetErrorMode()
	{
		// get PDO object and reset the error mode to what it was originally
		$pdo = $this->getPdo();
		$pdo->setAttribute(PDO::ATTR_ERRMODE, $this->_errMode);
	}

	// ########################################################################
	//  database queries
	// ########################################################################
	// these queries are potentially unsafe because the person using this library
	// can set the table to use, but there is no reliable way to escape SQL
	// fieldnames in PDO yet

	/**
	 * This method returns the query used to create a pgt storage table
	 *
	 * @return the create table SQL, no bind params in query
	 */
	protected function _createTableSql()
	{
		return 'CREATE TABLE ' . $this->getTable() . ' (pgt_iou VARCHAR(255) NOT NULL PRIMARY KEY, pgt VARCHAR(255) NOT NULL)';
	}

	/**
	 * This method returns the query used to store a pgt
	 *
	 * @return the store PGT SQL, :pgt and :pgt_iou are the bind params contained in the query
	 */
	protected function _storePgtSql()
	{
		return 'INSERT INTO ' . $this->getTable() . ' (pgt_iou, pgt) VALUES (:pgt_iou, :pgt)';
	}

	/**
	 * This method returns the query used to retrieve a pgt. the first column of the first row should contain the pgt
	 *
	 * @return the retrieve PGT SQL, :pgt_iou is the only bind param contained in the query
	 */
	protected function _retrievePgtSql()
	{
		return 'SELECT pgt FROM ' . $this->getTable() . ' WHERE pgt_iou = :pgt_iou';
	}

	/**
	 * This method returns the query used to delete a pgt.
	 *
	 * @return the delete PGT SQL, :pgt_iou is the only bind param contained in the query
	 */
	protected function _deletePgtSql()
	{
		return 'DELETE FROM ' . $this->getTable() . ' WHERE pgt_iou = :pgt_iou';
	}

	// ########################################################################
	//  PGT I/O
	// ########################################################################

	/**
	 * This method creates the database table used to store pgt's and pgtiou's
	 */
	public function createTable()
	{
		phpCAS::traceBegin();

		// initialize the PDO object for this method
		$pdo = $this->getPdo();
		$this->setErrorMode();

		try {
			$pdo->beginTransaction();

			$query = $pdo->query($this->_createTableSQL());
			$query->closeCursor();

			$pdo->commit();
		}
		catch(PDOException $e) {
			// attempt rolling back the transaction before throwing a phpCAS error
			try {
				$pdo->rollBack();
			}
			catch(PDOException $e) {}
			phpCAS::error('error creating PGT storage table: ' . $e->getMessage());
		}

		// reset the PDO object
		$this->resetErrorMode();

		phpCAS::traceEnd();
	}

	/**
	 * This method stores a PGT and its corresponding PGT Iou in the database. Echoes a
	 * warning on error.
	 *
	 * @param $pgt the PGT
	 * @param $pgt_iou the PGT iou
	 */
	public function write($pgt, $pgt_iou)
	{
		phpCAS::traceBegin();

		// initialize the PDO object for this method
		$pdo = $this->getPdo();
		$this->setErrorMode();

		try {
			$pdo->beginTransaction();

			$query = $pdo->prepare($this->_storePgtSql());
			$query->bindValue(':pgt', $pgt, PDO::PARAM_STR);
			$query->bindValue(':pgt_iou', $pgt_iou, PDO::PARAM_STR);
			$query->execute();
			$query->closeCursor();

			$pdo->commit();
		}
		catch(PDOException $e) {
			// attempt rolling back the transaction before throwing a phpCAS error
			try {
				$pdo->rollBack();
			}
			catch(PDOException $e) {}
			phpCAS::error('error writing PGT to database: ' . $e->getMessage());
		}

		// reset the PDO object
		$this->resetErrorMode();

		phpCAS::traceEnd();
	}

	/**
	 * This method reads a PGT corresponding to a PGT Iou and deletes the
	 * corresponding db entry.
	 *
	 * @param $pgt_iou the PGT iou
	 *
	 * @return the corresponding PGT, or FALSE on error
	 */
	public function read($pgt_iou)
	{
		phpCAS::traceBegin();
		$pgt = FALSE;

		// initialize the PDO object for this method
		$pdo = $this->getPdo();
		$this->setErrorMode();

		try {
			$pdo->beginTransaction();

			// fetch the pgt for the specified pgt_iou
			$query = $pdo->prepare($this->_retrievePgtSql());
			$query->bindValue(':pgt_iou', $pgt_iou, PDO::PARAM_STR);
			$query->execute();
			$pgt = $query->fetchColumn(0);
			$query->closeCursor();

			// delete the specified pgt_iou from the database
			$query = $pdo->prepare($this->_deletePgtSql());
			$query->bindValue(':pgt_iou', $pgt_iou, PDO::PARAM_STR);
			$query->execute();
			$query->closeCursor();

			$pdo->commit();
		}
		catch(PDOException $e) {
			// attempt rolling back the transaction before throwing a phpCAS error
			try {
				$pdo->rollBack();
			}
			catch(PDOException $e) {}
			phpCAS::trace('error reading PGT from database: ' . $e->getMessage());
		}

		// reset the PDO object
		$this->resetErrorMode();

		phpCAS::traceEnd();
		return $pgt;
	}

	/** @} */

}

?>