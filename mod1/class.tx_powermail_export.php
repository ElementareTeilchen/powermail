<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Alex Kellner, Mischa Heissmann <alexander.kellner@einpraegsam.net, typo3.YYYY@heissmann.org>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once('../lib/class.tx_powermail_functions_div.php');

class tx_powermail_export {

	/**
	 * Extension key
	 *
	 * @var string
	 */
	var $extKey = 'powermail';
	
	/**
	 * Timeformat for displaying date
	 *
	 * @var string
	 */
	var $dateformat = 'Y-m-d';
	
	/**
	 * Timeformat for displaying time
	 *
	 * @var string
	 */
	var $timeformat = 'H:i:s';
	
	/**
	 * Separator for csv
	 *
	 * @var string
	 */
	var $seperator = ';'; // 
	
	/**
	 * Filename of exported CSV file
	 *
	 * @var string
	 */
	var $csvfilename = 'powermail_export_';
	
	/**
	 * Activate CSV file compressing to .gz
	 *
	 * @var bool
	 */
	var $zip = 1;
	
	/**
	 * $LANG object
	 *
	 * @var language
	 */
	var $LANG = null;
	
	/**
	 * Encoding for data output
	 *
	 * @var string
	 */
	var $outputEncoding;
	
	/**
	 * Module's TSconfig
	 *
	 * @var array
	 */
	var $tsconfig;
	
	/**
	 * Order for export
	 *
	 * @var array
	 */
	var $rowconfig = array (
		'number' => '#', 
		'date' => 'Date', 
		'time' => 'Time', 
		'uid' => 'all', 
		'sender' => 'Sender email', 
		'senderIP' => 'Sender IP address', 
		'recipient' => 'Recipient email', 
		'subject_r' => 'Email subject', 
		'formid' => 'Page ID', 
		'UserAgent' => 'UserAgent', 
		'Referer' => 'Referer', 
		'SP_TZ' => 'Sender location'
	);
	
	/**
	 * Startdate
	 *
	 * @var string
	 */
	var $default_start;
	
	/**
	 * Enddate
	 *
	 * @var string
	 */
	var $default_end;

	/**
	 * Dispatcher main method for export
	 *
	 * @param string $export
	 * @param int $pid
	 * @param language $LANG
	 * @return string
	 */
	function main($export, $pid = 0, $LANG = '') {
		$this->pid = $pid;
		
		$this->startdate = t3lib_div::_GET('startdate');
		if(isset($this->default_start)){
			$this->startdate = $this->default_start;
		}
		
		$this->enddate = t3lib_div::_GET('enddate');
		if(isset($this->default_end)){
			$this->enddate = $this->default_end;
		}
		
		$this->LANG = $LANG;
		$content = ''; 
		$i = 0;
		$this->tsconfig = t3lib_BEfunc::getModTSconfig($this->pid, 'tx_powermail_mod1');
		
		if(!empty($this->tsconfig['properties']['config.']['export.']['dateformat'])){
			$this->dateformat = $this->tsconfig['properties']['config.']['export.']['dateformat'];
		}
		
		if(!empty($this->tsconfig['properties']['config.']['export.']['timeformat'])) {
			$this->timeformat = $this->tsconfig['properties']['config.']['export.']['timeformat'];
		}
		
		$this->useTitle = 1;
		if($this->tsconfig['properties']['config.']['export.']['useTitle'] == 0 && isset($this->tsconfig['properties']['config.']['export.']['useTitle'])){
			$this->useTitle = $this->tsconfig['properties']['config.']['useTitle'];
		}
		
		if(count($this->tsconfig['properties']['export.']) > 0){
			$this->rowconfig = $this->tsconfig['properties']['export.'];
		}
		
		// Define output encoding -> No encoding is defined, set default
		if (empty($this->tsconfig['properties']['config.']['export.']['encoding.'][$export])) { 
			// Set LATIN1 for csv
			if ($export == 'csv'){
				$this->outputEncoding = 'latin1'; 
				
			// Take standard charset
			} else {
				$this->outputEncoding = $this->LANG->charSet; 
			}
		} else {
			$this->outputEncoding = $this->tsconfig['properties']['config.']['export.']['encoding.'][$export];
		}

		$select = '*';
		$from = 'tx_powermail_mails';
		$where = 'pid = ' . $this->pid . ' AND hidden = 0 AND deleted = 0 AND crdate > ' . strtotime($this->startdate) . ' AND crdate < ' . strtotime($this->enddate);
		$groupBy = $limit = '';
		$orderBy = 'crdate DESC';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($select, $from, $where, $groupBy, $orderBy, $limit);
		
		// If on current page is a result
		if ($res) {
			if ($export == 'xls' || $export == 'table' || $export == 'email') {
				$table = '<table>';
				
				// Get first row for "$this->setTitle"-call and reset database result pointer
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				$GLOBALS['TYPO3_DB']->sql_data_seek($res, 0);
				
				$table .= $this->setTitle($export, $row);
				while (($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))) {
					if ($row['piVars']) {
						if ($this->outputEncoding != 'utf-8') {
							 // Change to utf8 to avoid problems with umlauts
							if (method_exists($this->LANG->csConvObj, 'conv')){
								$row['piVars'] = $this->LANG->csConvObj->conv($row['piVars'], $this->LANG->charSet, 'utf-8');
							}
						}
						$values = t3lib_div::xml2array($row['piVars'], 'piVars');
						
						$i++;
						$table .= '<tr>';
						foreach ($this->rowconfig as $key => $value) {
							 // If current row is number
							if ($key == 'number'){
								$table .= '<td>' . $i . '.</td>';
							
							// If current row is date		
							} elseif ($key == 'date') {
								$table .= '<td>' . date($this->dateformat, $row['crdate']) . '</td>'; 
								
							// If current row is time
							} elseif ($key == 'time') {
								$table .= '<td>' . date($this->timeformat, $row['crdate']) . '</td>'; 
							
							// If current row should show all dynamic values (piVars)
							} elseif ($key == 'uid') { 
								if (isset($values) && is_array($values)) {
									
									// One loop for every piVar
									foreach ($values as $key => $value) { 
										if (!is_array($value)) {
											$table .= '<td>' . $this->cleanString($value, $export) . '</td>';
										} else {
											// One loop for every piVar in second level
											foreach ($values[$key] as $key2 => $value2) {
												$table .= '<td>' . $this->cleanString($value2, $export) . '</td>';
											}
										}
									}
								}
								
							// Dynamic value like uid45
							} elseif (is_numeric(str_replace(array('uid', '_'), '', $key))) { 
								
								// Explode uid44_0 to uid44 and 0
								$newkey = explode('_', $key); 
								// piVars in first level
								if (!is_array($values[$newkey[0]])) { 
									if (!empty($values[$key])) {
										$table .= '<td>' . $this->cleanString($values[$key], $export) . '</td>';
									} else {
										$table .= '<td></td>';
									}
									
								// PiVars in second level	
								} else {
									if (!empty($values[$newkey[0]][$newkey[1]])) {
										$table .= '<td>' . $this->cleanString($values[$newkey[0]][$newkey[1]], $export) . '</td>';
									} else {
										$table .= '<td></td>';
									}
								}
							} else {
								$table .= '<td>' . $row[$key] . '</td>';
							}
						}
						$table .= '</tr>';
					}
				}
				$table .= '</table>';
				
			// If CSV Export	
			} elseif ($export == 'csv') { 
				
				$table .= $this->setTitle($export,$row);
				while (($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))) {
					if($row['piVars']) {
						$i++;
						if ($this->LANG->charSet != 'utf-8') {
						
							// Change to utf8 to avoid problems with umlauts
							if (method_exists($this->LANG->csConvObj, 'conv')) {
								$row['piVars'] = $this->LANG->csConvObj->conv($row['piVars'], $this->LANG->charSet, 'utf-8'); 
							}
						}
						$values = t3lib_div::xml2array($row['piVars'], 'piVars');						
						
						foreach ($this->rowconfig as $key => $value) {
							// If current row is number
							if ($key == 'number') {
								$table .= '"' . $i . '."' . $this->seperator; 
								
							// If current row is date	
							} elseif ($key == 'date') {
								$table .= '"' . date($this->dateformat, $row['crdate']) . '"' . $this->seperator; 
							
							// If current row is time
							} elseif ($key == 'time') {
								$table .= '"' . date($this->timeformat, $row['crdate']) . '"' . $this->seperator; 
								
							// If current row should show all dynamic values (piVars)	
							} elseif ($key == 'uid') { 
								if (isset($values) && is_array($values)) {
									// One loop for every piVar
									foreach ($values as $key => $value) {
										if (!is_array($value)){
											$table .= '"' . $this->cleanString($value) . '"' . $this->seperator;
										} else {
											
											// One loop for every piVar in second level
											foreach ($values[$key] as $value2) { 
												$table .= '"' . $this->cleanString($value2) . '"' . $this->seperator;
											}
										}
									}
								}
								
							// Dynamic value like uid45
							} elseif (is_numeric(str_replace(array('uid','_'),'',$key))) {
								// Explode uid44_0 to uid44 and 0
								$newkey = explode('_',$key); 
								
								// PiVars in first level
								if (!is_array($values[$newkey[0]])) { 
									if (!empty($values[$key])) {
										$table .= '"' . $this->cleanString($values[$key]) . '"' . $this->seperator;
									} else {
										$table .= '" "' . $this->seperator;
									}
									
								// PiVars in second level
								} else { 
									if (!empty($values[$newkey[0]][$newkey[1]])) {
										$table .= '"' . $this->cleanString($values[$newkey[0]][$newkey[1]]) . '"' . $this->seperator;
									} else {
										$table .= '" "' . $this->seperator;
									}
								}
							} else {
								$table .= '"' . $row[$key] . '"' . $this->seperator;
							}
						}
						
						// Delete last ,
						$table = substr($table,0,-1); 
						$table .= "\n";
					}
				}
			}
		}
		
		$hash = $this->getHash();
		if ($export == 'xls') {
			$content .= header("Content-type: application/vnd-ms-excel");
			$content .= header("Content-Disposition: attachment; filename=export.xls");
			$content .= $table;
		
		} elseif ($export == 'csv') {
			// Write to typo3temp and if success returns FALSE
			if (!t3lib_div::writeFileToTypo3tempDir(PATH_site . 'typo3temp/' . $this->csvfilename . $hash . '.csv', $table)) { 
				$content .= '<strong>' . $this->LANG->getLL('export_download_success') . '</strong><br />';
				$this->gzcompressfile(PATH_site . 'typo3temp/' . $this->csvfilename . $hash . '.csv');
				$content .= '<a href="' . t3lib_div::getIndpEnv('TYPO3_SITE_URL') . 'typo3temp/' . $this->csvfilename . $hash . '.csv' . '" target="_blank"><u>' . $this->LANG->getLL('export_download_download') . '</u></a><br />';
				$content .= '<a href="' . t3lib_div::getIndpEnv('TYPO3_SITE_URL') . 'typo3temp/' . $this->csvfilename . $hash . '.csv' . '.gz" target="_blank"><u>' . $this->LANG->getLL('export_download_downloadZIP') . '</u></a><br />';
			
			} else {
				$content .= t3lib_div::writeFileToTypo3tempDir(PATH_site . 'typo3temp/' . $this->csvfilename . $hash . '.csv', $table);
			}
		
		} elseif ($export == 'email') {
			// Write to typo3temp and if success returns FALSE
			if (!t3lib_div::writeFileToTypo3tempDir(PATH_site . 'typo3temp/' . $this->csvfilename . $hash . '.xls', $table)) { 
				if ($i > 0) {
					$content .= 'typo3temp/' . $this->csvfilename . $hash . '.xls';
				}
				
			} else {
				$content .= t3lib_div::writeFileToTypo3tempDir(PATH_site . 'typo3temp/' . $this->csvfilename . $hash . '.xls', $table);
			}
			
		} elseif ($export == 'table') {
			$content .= $table;
		
		// Not supported method
		} else { 
			$content = 'Wrong export method chosen!';
		}
		
		// Delete all exported mails now
		if (t3lib_div::_GET('delafterexport') == 1) { 
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery (
				'tx_powermail_mails',
				'pid = ' . $this->pid . ' AND hidden = 0 AND deleted = 0 AND crdate > ' . strtotime($this->startdate) . ' AND crdate < ' . strtotime($this->enddate),
				array (
					'deleted' => 1
				)
			);
		}
		
		return $content;
	}

	/**
	 * Compress a file
	 *
	 * @param string $source
	 * @param bool $level
	 * @return string
	 */
	function gzcompressfile($source, $level = false) { 
		$dest = $source . '.gz';
		$mode = 'wb' . $level;
		$error = false;
		
		if (($fp_out = gzopen($dest, $mode))) {
			if (($fp_in = fopen($source, 'rb'))) {
				while (!feof($fp_in)) {
					gzwrite($fp_out, fread($fp_in, 1024 * 512));
				}
				
				fclose($fp_in);
			} else {
				$error = true;
			}
			gzclose($fp_out);
			
		} else {
			$error = true;
		}
		
		if ($error) {
			return false;
		} else {
			return $dest;
		}
	}
	
	/**
	 * Set title
	 *
	 * @param string $export
	 * @param array $row
	 * @return string
	 */
	function setTitle($export, $row) {
		// If title should be used
		if ($this->useTitle == 1 && isset($this->rowconfig)) {	
			if ($this->LANG->charSet != 'utf-8') {
				if (method_exists($this->LANG->csConvObj, 'conv')) {
					$row['piVars'] = $this->LANG->csConvObj->conv($row['piVars'], $this->LANG->charSet, 'utf-8');
				}
			}
			$values = t3lib_div::xml2array($row['piVars'], 'pivars');
			
			$table = '<tr>';
			if($export == 'csv') {
				$table = '';
			}
			foreach ($this->rowconfig as $key => $value) {
				if ($this->outputEncoding != 'utf-8') {
					if (method_exists($this->LANG->csConvObj, 'conv')) {
						$value = $this->LANG->csConvObj->conv($value, 'utf-8', $this->outputEncoding);
					}
				}
				
				// Static values
				if ($key != 'uid') { 
					if ($export == 'csv') {
						$table .= '"' . $value . '"' . $this->seperator;
						
					// HTML and EXCEL only
					} else { 
						$table .= '<td><b>' . $value . '</b></td>';
					}
					
				} else {
					if (isset($values) && is_array($values)) {
						foreach ($values as $key => $value) {
							$label = $this->GetLabelfromBackend($key, $value);
							
							if ($this->outputEncoding != 'utf-8') {
								if (method_exists($this->LANG->csConvObj, 'conv')) {
									$label = $this->LANG->csConvObj->conv($label, 'utf-8', $this->outputEncoding);
								}
							}
							
							if (!is_array($value)) {
								if ($export == 'csv') {
									$table .= '"' . $this->cleanString($label) . '"' . $this->seperator;
									
								} else {
									$table .= '<td><b>' . $label . '</b></td>';
								}
							}
						}
					}
				}
			}
			
			$table .= '</tr>';
			if($export == 'csv') {
				$table = substr($table,0,-1) . "\n";
			}
			
			if (!empty($table)) {
				return $table;
			}
		}
	}
    
    /**
     * Method GetLabelfromBackend() to get label to current field for emails and thx message
     *
     * @param string $name
     * @param string $value
     * @return string
     */
    function GetLabelfromBackend($name, $value) {
    	
    	// $name like uid55
		if (strpos($name, 'uid') !== FALSE) {
			$uid = str_replace('uid', '', $name);

			$select = 'f.title';
			$from = '
				tx_powermail_fields f 
				LEFT JOIN tx_powermail_fieldsets fs 
				ON (
					f.fieldset = fs.uid 
				) 
				LEFT JOIN tt_content c 
				ON (
					c.uid = fs.tt_content
				)';
			$where = '
				c.deleted=0 
				AND c.hidden=0
				AND (
					c.starttime<=' . time() . '
				) 
				AND (
					c.endtime=0 
					OR c.endtime>' . time() . '
				) 
				AND (
					c.fe_group="" 
					OR c.fe_group IS NULL 
					OR c.fe_group="0" 
					OR (
						c.fe_group LIKE "%,0,%" 
						OR c.fe_group LIKE "0,%" 
						OR c.fe_group LIKE "%,0" 
						OR c.fe_group="0"
					) 
					OR (
						c.fe_group LIKE "%,-1,%" 
						OR c.fe_group LIKE "-1,%" 
						OR c.fe_group LIKE "%,-1" 
						OR c.fe_group="-1"
					)
				)
				AND f.uid = ' . $uid . ' 
				AND f.hidden = 0 
				AND f.deleted = 0';
			$groupBy = $orderBy = $limit = '';
			// GET title where fields.flexform LIKE <value index="vDEF">vorname</value>
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($select, $from, $where, $groupBy, $orderBy, $limit);

			if ($res) {
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			}

			// If title was found return it
			if (isset($row['title'])) {
				return $row['title']; 
				
			// If no title was found return 	
			} else if ($uid < 100000) {
				return 'POWERMAIL ERROR: No title to current field found in DB'; 
			}
			
		// No uid55 so return $name
		} else { 
			return $name;
		}
    }
	
	/**
	 * Method cleanString() cleans up a string
	 *
	 * @param string $string
	 * @param string $export
	 * @return string
	 */
	function cleanString($string, $export = 'csv') {
		switch ($export){
			case 'csv':
				$string = str_replace(array("\n\r", "\r\n", "\n", "\r"), '', $string);
				$string = str_replace('"', "'", $string);
				$string = stripslashes($string);
				if ($this->outputEncoding == 'utf-8') {
					$string = utf8_decode($string);
				}
			break;
			case 'xls':
				$string = stripslashes($string);
				if ($this->outputEncoding == 'utf-8') {
					$string = utf8_decode($string);
				}
			break;
			default:
				$string = stripslashes($string);
		}
		
    	return $string;
    }
	
	/**
	 * Method getHash() returns random hash code
	 *
	 * @return string
	 */
	function getHash() {
		return md5(uniqid(rand(), true));
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/powermail/mod1/class.tx_powermail_export.php']) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/powermail/mod1/class.tx_powermail_export.php']);
}
?>