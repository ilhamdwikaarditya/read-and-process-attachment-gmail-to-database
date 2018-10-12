<?php 

public function mandiri_transaction()
    {
		date_default_timezone_set('Asia/Jakarta'); 
		set_time_limit(3000);
		$imapServ = "imap.gmail.com";
		$imapPort = "993";
		$imapUser = "email@gmail.com";
		$imapPass = "passwordemail";
		$mbox = imap_open("{" . $imapServ . ":" . $imapPort . "/imap/ssl/novalidate-cert}INBOX", $imapUser, $imapPass) or die('Cannot connect to Gmail: ' . imap_last_error());
		if (isset($_GET['email'])) {
			$result = imap_fetchbody($mbox, $_GET['email'], 1);
			echo "<p>$result</p>";
			echo "<br>";
			echo "<b><a href=\"" . $_SERVER['SCRIPT_NAME'] . "\">Back To List</a></b>";

		} else {
			
			$imapEmails = imap_search($mbox, 'UNSEEN SUBJECT "EVERYTHING YOUR WANT SUBJECT"');
			if($imapEmails){
				
				foreach ($imapEmails as $Email) {
					$email_overview = imap_fetch_overview($mbox, round($Email), 0);
						foreach ($email_overview as $v) {
							echo "<a href=\"" . $_SERVER['SCRIPT_NAME'] . "?email=" . $v->uid . "\"><b>From:</b>" . $v->from . " <b>Subject: </b>" . $v->subject . " <b>Date: </b>" . $v->date . "</a>";
							echo "<br>";
						}

					$overview = imap_fetch_overview($mbox,$Email,0);
					$message = imap_fetchbody($mbox,$Email,2);
					$structure = imap_fetchstructure($mbox, $Email);
					$attachments = array();
					
					if(isset($structure->parts) && count($structure->parts)) 
					{
						for($i = 0; $i < count($structure->parts); $i++) 
						{
							$attachments[$i] = array(
								'is_attachment' => false,
								'filename' => '',
								'name' => '',
								'attachment' => ''
							);

							if($structure->parts[$i]->ifdparameters) 
							{
								foreach($structure->parts[$i]->dparameters as $object) 
								{
									if(strtolower($object->attribute) == 'filename') 
									{
										$attachments[$i]['is_attachment'] = true;
										$attachments[$i]['filename'] = $object->value;
									}
								}
							}

							if($structure->parts[$i]->ifparameters) 
							{
								foreach($structure->parts[$i]->parameters as $object) 
								{
									if(strtolower($object->attribute) == 'name') 
									{
										$attachments[$i]['is_attachment'] = true;
										$attachments[$i]['name'] = $object->value;
									}
								}
							}

							if($attachments[$i]['is_attachment']) 
							{
								$attachments[$i]['attachment'] = imap_fetchbody($mbox, $Email, $i+1);
								if($structure->parts[$i]->encoding == 3) 
								{ 
									$attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
								}
								elseif($structure->parts[$i]->encoding == 4) 
								{ 
									$attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
								}
							}
						}
					}
					#THIS QUERY JUST FOR ME. YOU CAN DELETE
					#ONLY FOR MY CASE. YOU CAN DELETE
					$this->db->query("DROP TABLE IF EXISTS mandiri_log_email_temp; ");
					$this->db->query("CREATE TEMPORARY TABLE `mandiri_log_email_temp` (
									  `ID` int(11) NOT NULL AUTO_INCREMENT,
									  `FILENAME` varchar(255) DEFAULT NULL,
									  `BANK` varchar(255) DEFAULT NULL,
									  `JENIS` varchar(255) DEFAULT NULL,
									  `HEADER` varchar(255) DEFAULT NULL,
									  `URUT_KIRIM` varchar(255) DEFAULT NULL,
									  `BODY_TRANSACTION` varchar(255) DEFAULT NULL,
									  `FOOTER_MT1` varchar(255) DEFAULT NULL,
									  `FOOTER_MT2` varchar(255) DEFAULT NULL,
									  `JAM_BACA` datetime DEFAULT NULL,
									  PRIMARY KEY (`ID`) USING BTREE
									) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=latin1 ROW_FORMAT=COMPACT;");
					#END QUERY YOU CAN DELETE
					foreach($attachments as $attachment)
					{
						if($attachment['is_attachment'] == 1)
						{
							$filename = $attachment['name'];
							if(empty($filename)) $filename = $attachment['filename'];

							if(empty($filename)) $filename = time() . ".dat";
							$folder = "attachment";
							if(!is_dir($folder))
							{
								 mkdir($folder);
							}
							$fp = fopen("./". $folder ."/". $Email . "-" . $filename, "w+");
							fwrite($fp, $attachment['attachment']);
							$isifile = $attachment['attachment'];
							fclose($fp);
							$linefile = explode("\n", $isifile);
							
							#PROCESS READ AND INSERT INTO DATABASE
							#FROM THIS LINE YOU CAN ADJUST WHAT YOU WANT
							$count = count($linefile);
							if($count > 8)
							{
								$input = ':61:';
								$result = array_filter($linefile, function ($item) use ($input) {
									if (stripos($item, $input) !== false) {
										return true;
									}
									return false;
								});
								$count61 = count($result);
								for($i = 6; $i < 6 + ($count61*2); $i++){
									$j = $i % 2;
									if($j == 0){
										$a = $i;
										$b = $i+1;
										$cek940_or_942 = substr($linefile[0],26,3);
										if($cek940_or_942 == 942){
											$this->db->query("INSERT INTO mandiri_log_email_temp (FILENAME, BANK, JENIS, HEADER, URUT_KIRIM, BODY_TRANSACTION, FOOTER_MT1, FOOTER_MT2, JAM_BACA)
															VALUES ('$filename', '".$linefile[0]."', '".$linefile[1]."', 'NULL', '".$linefile[3]."', '".$linefile[$a]."\n".$linefile[$b]."', '".$linefile[5]."', '".$linefile[4]."', '".date('Y-m-d H:i:s')."' ) ");
										}
									}
								}
								
								if($cek940_or_942 == 942)
								{	
									$gotolog = $this->db->query("INSERT INTO MANDIRI_LOG_EMAIL (FILENAME, BANK, JENIS, HEADER, URUT_KIRIM, BODY_TRANSACTION, FOOTER_MT1, FOOTER_MT2, JAM_BACA) 
															SELECT FILENAME, BANK, JENIS, HEADER, URUT_KIRIM, BODY_TRANSACTION, FOOTER_MT1, FOOTER_MT2, JAM_BACA FROM mandiri_log_email_temp WHERE BODY_TRANSACTION like '%UBP%' AND FILENAME = '$filename' ");
									if($gotolog)
									{
										$log = $this->db->query("SELECT * FROM MANDIRI_LOG_EMAIL WHERE FILENAME = '$filename' ");
										foreach($log->result() as $r)
										{
											$bank 	= $r->BANK;
											$jenis	= $r->JENIS;
											$foot1	= $r->FOOTER_MT1;
											$foot2	= $r->FOOTER_MT2;

											$linebody = explode("\n", $r->BODY_TRANSACTION);
											$body1  = $linebody[0];
											$body2  = $linebody[1];
											
											$cekbody2 = strlen($body2);
											if($cekbody2 == 31){ 
												$id_bayarx = substr($body2,-7); 
											}
											else if($cekbody2 == 32){ 
												$id_bayarx = substr($body2,-8); 
											}
											else{ 
												$id_bayarx = substr($body2,-13);
											}
											#DELETE WHITE SPACE
											$id_bayar = preg_replace('/\s+/', '', $id_bayarx); 
											
											$cektgl  = substr($body1,4,10);
											$th 	  = substr($cektgl,0,2);
											$bln	  = substr($cektgl,2,2);
											$tgl	  = substr($cektgl,4,2);
											$jam	  = substr($cektgl,6,2);
											$mnt	  = substr($cektgl,8,2);
											$dt 	  = DateTime::createFromFormat('y', $th);
											$thn 	  = $dt->format('Y');
											$tgl_bayar = date('Y-m-d H:i:s');
											
											$ref_bayar = substr($body1,-20);
											$loket_bayar = substr($body2,7,4);
											
											$replace   = array("C", "NTRF");
											$cekbody1a = str_replace($replace,"|",$body1);
											$cekbody1b = explode("|", $cekbody1a);
											$rupiah_bayar = $cekbody1b[1];
											
											$cek_id_bayar = $this->db->query("select id_bayar, tgl_bayar from MANDIRI_TRANSAKSI where id_bayar = '".$id_bayar."' and date(TGL_BAYAR) = date(".$tgl_bayar.") ");
											if($cek_id_bayar->num_rows() != '1')
											{
												$this->db->query("INSERT INTO MANDIRI_TRANSAKSI (ID_BAYAR, TGL_BAYAR, REF_BAYAR, LOKET_BAYAR, RUPIAH_BAYAR, FILENAME, STATUS_EKSEKUSI)
															 VALUES ('$id_bayar', '$tgl_bayar', '$ref_bayar', '$loket_bayar', '$rupiah_bayar', '$filename', 'X') ");
											
												$master = $this->db->query("SELECT ID_BAYAR,NAMA,THBLMIN,THBLMAX,JML_LEMBAR,RUPIAH_INVOICE, THBL_UPLOAD FROM MANDIRI_MASTER WHERE ID_BAYAR = '$id_bayar' AND THBL_UPLOAD = (SELECT MAX(THBL_UPLOAD) FROM MANDIRI_MASTER) "); 
												foreach($master->result() as $rm) 
												{
													$id_bayarkan = $rm->ID_BAYAR;
													$thblmin  = $rm->THBLMIN;
													$thblmax  = $rm->THBLMAX;
													$rupiah_invoice = $rm->RUPIAH_INVOICE;
												
													$cek_mantrans =  $this->db->query("SELECT ID_BAYAR, RUPIAH_BAYAR, MAX(TGL_BAYAR) TGL_BAYAR FROM MANDIRI_TRANSAKSI WHERE ID_BAYAR = '$id_bayarkan' ");
													$total_rup = $cek_mantrans->row("RUPIAH_BAYAR");
												
													if(strlen($id_bayar) == '7')
													{
														$cek_masrek=  $this->db->query("SELECT ID_CUST, SUM(TOTAL_INVOICE) TOTAL_INVOICE, STATUS_LUNAS FROM MASTER_REKENING WHERE ID_CUST = '$id_bayarkan' AND STATUS_LUNAS = '0' ");
														$status_lunas = $cek_masrek->row("STATUS_LUNAS");
														$total_inv = $cek_masrek->row("TOTAL_INVOICE");
														
														if($status_lunas == '' or $status_lunas == NULL or $status_lunas != '0')
														{  
															$this->db->query("UPDATE MANDIRI_TRANSAKSI SET STATUS_EKSEKUSI = 'GAGAL KARENA INVOICE SUDAH DILUNASI SEBELUMNYA' WHERE ID_BAYAR = '$id_bayarkan' AND REF_BAYAR = '$ref_bayar' ");
														}
														else if($total_inv != $total_rup)
														{
															$this->db->query("UPDATE MANDIRI_TRANSAKSI SET STATUS_EKSEKUSI = 'GAGAL KARENA TOTAL INVOICE BERBEDA' WHERE ID_BAYAR = '$id_bayarkan' AND REF_BAYAR = '$ref_bayar' ");
														}
														else
														{
															$pelunasan = $this->db->query("UPDATE MASTER_REKENING SET STATUS_LUNAS = '1', TGL_LUNAS = '$tgl_bayar', REF_LUNAS = '$ref_bayar', USER_LUNAS = 'MANDIRI', LOKET_LUNAS = '$loket_bayar' WHERE ID_CUST = '$id_bayarkan' AND STATUS_LUNAS = '0' AND (THBLREK BETWEEN '$thblmin' AND '$thblmax') ");
															if($pelunasan === TRUE){
																$this->db->query("UPDATE MANDIRI_TRANSAKSI SET STATUS_EKSEKUSI = 'LUNAS' WHERE ID_BAYAR = '$id_bayarkan' AND REF_BAYAR = '$ref_bayar' ");
																$this->db->query("UPDATE epi_cargo.ws_epi SET STATUS_LUNAS = '1', TGL_LUNAS = '$tgl_bayar', REF_LUNAS = '$ref_bayar', USER_LUNAS = 'MANDIRI' WHERE ID_CUST = '$id_bayarkan' AND STATUS_LUNAS = '0' AND (THBLREK BETWEEN '$thblmin' AND '$thblmax') ");
																echo "<br> id_customer :".$id_bayarkan.", total_rup : ".$total_rup.", total_inv : ".$total_inv."<br>";
															}
														}
													}
													else
													{
														$cek_masrek=  $this->db->query("SELECT ID_CUST, ID_LANG, SUM(TOTAL_INVOICE) TOTAL_INVOICE, STATUS_LUNAS FROM MASTER_REKENING WHERE ID_LANG = '$id_bayarkan' AND STATUS_LUNAS = '0' ");
														$status_lunas = $cek_masrek->row("STATUS_LUNAS");
														$total_inv = $cek_masrek->row("TOTAL_INVOICE");
														
														if($status_lunas == '' or $status_lunas == NULL or $status_lunas != '0' )
														{  
															$this->db->query("UPDATE MANDIRI_TRANSAKSI SET STATUS_EKSEKUSI = 'GAGAL KARENA INVOICE SUDAH DILUNASI SEBELUMNYA' WHERE ID_BAYAR = '$id_bayarkan' AND REF_BAYAR = '$ref_bayar' ");
														
														}
														else if($total_inv != $total_rup)
														{
															$this->db->query("UPDATE MANDIRI_TRANSAKSI SET STATUS_EKSEKUSI = 'GAGAL KARENA TOTAL INVOICE BERBEDA' WHERE ID_BAYAR = '$id_bayarkan' AND REF_BAYAR = '$ref_bayar' ");
														
														}
														else
														{
															$pelunasan = $this->db->query("UPDATE MASTER_REKENING SET STATUS_LUNAS = '1', TGL_LUNAS = '$tgl_bayar', REF_LUNAS = '$ref_bayar', USER_LUNAS = 'MANDIRI', LOKET_LUNAS = '$loket_bayar' WHERE ID_LANG = '$id_bayarkan' AND STATUS_LUNAS = '0' AND (THBLREK BETWEEN '$thblmin' AND '$thblmax') ");
															if($pelunasan === TRUE){
																$this->db->query("UPDATE MANDIRI_TRANSAKSI SET STATUS_EKSEKUSI = 'LUNAS' WHERE ID_BAYAR = '$id_bayarkan' AND REF_BAYAR = '$ref_bayar' ");
																$this->db->query("UPDATE epi_cargo.ws_epi SET STATUS_LUNAS = '1', TGL_LUNAS = '$tgl_bayar', REF_LUNAS = '$ref_bayar', USER_LUNAS = 'MANDIRI' WHERE ID_LANG = '$id_bayarkan' AND STATUS_LUNAS = '0' AND (THBLREK BETWEEN '$thblmin' AND '$thblmax') ");
																echo "<br> id_langganan :".$id_bayarkan.", total_rup : ".$total_rup.", total_inv : ".$total_inv."<br>";
															}
														}
													}
												}
											}
										}
									}
								}
							}
							else
							{
								$this->db->query("INSERT INTO MANDIRI_LOG_EMAIL (FILENAME, BANK, JENIS, HEADER, URUT_KIRIM)
													VALUES ('$filename', '".$linefile[0]."', '".$linefile[1]."', 'NULL', '".$linefile[3]."' ) ");
							}
						}
						#THIS LINE ENDING PROCESS SAVE ATTACHMENT TO FOLDER AND READ FOR PROCESS TO DATABASE
					}
				}
			}
			imap_close($mbox);
		}
	}