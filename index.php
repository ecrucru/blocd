<?php
	//-------------------------
	// Blocd - Manage your spams
	// The Unlicense
	// https://github.com/ecrucru/blocd/
	//-------------------------


	//-- Configuration
	if (!file_exists('config.php'))
		die('Blocd - Please activate the file "config.php" based on the provided sample');
	include 'config.php';
	define('FILE_RAW', 'blocklist.txt');
	define('FILE_POSTFIX', 'blocklist_postfix');
	set_time_limit(0);


	//-- Connection
	@mysql_connect(DB_HOST, DB_USER, DB_PWD) or die('Blocd - Verify your DB credentials in the configuration file');
	mysql_select_db(DB_DB);
	mysql_query("SET NAMES 'utf8'");


	//-- Create the local tables
	$tables = array();
	$allData = mysql_query('SHOW TABLES');
	while ($data = mysql_fetch_array($allData))
		$tables[] = $data[0];
	if (!in_array('blocd_domains', $tables))
		mysql_query("	CREATE TABLE `blocd_domains` (
						`Domain` VARCHAR(255) NOT NULL,
						`OK` ENUM('', 'X') NOT NULL,
						PRIMARY KEY (`Domain`))");
	if (!in_array('blocd_mbox`', $tables))
		mysql_query("	CREATE TABLE `blocd_mbox` (
						`Email` varchar(255) NOT NULL default '',
						`Host` varchar(255) NOT NULL default '',
						`Password` varchar(255) NOT NULL default '',
						`Folder` varchar(255) NOT NULL default '',
						`Enabled` enum('','X') NOT NULL default 'X',
						PRIMARY KEY (`Email`))");
	unset($data, $allData, $tables);


	//-- Functions
	function termination()
	{
		mysql_close();
	}

	function getGet($pName, $pDefault)
	{
		$val = (isset($_GET[$pName]) ? stripslashes($_GET[$pName]) : '');
		return ($val != '' ? $val : $pDefault);
	}

	function getPost($pName, $pDefault)
	{
		$val = (isset($_POST[$pName]) ? stripslashes($_POST[$pName]) : '');
		return ($val != '' ? $val : $pDefault);
	}

	function getDomain($pHost)
	{
		if (!preg_match('/([^\.]+\.[^\.]+)$/', $pHost, $data))
			return '';
		return (preg_match('/^[a-z0-9-\.]+$/i', $data[1]) ? $data[1] : '');
	}

	function _file_put_contents($pFile, $pData)
	{
		if ($pFile == '')
			return false;
		if (function_exists('file_put_contents'))		// PHP 5+
			return (file_put_contents($pFile, $pData) === false ? false : true);
		else
		{
			$handle = fopen($pFile, 'w');
			if ($handle === false)
				return false;
			fwrite($handle, $pData);
			fclose($handle);
			return true;
		}
	}


	//-- AJAX tasks
	$action = getGet('action', '');
	switch ($action)
	{
		case 'bg_ok':
		{
			$domain = getGet('domain', '');
			mysql_query(sprintf("UPDATE blocd_domains SET OK='X' WHERE Domain='%s'", addslashes($domain)));
			termination();
			return;
		}

		case 'bg_ko':
		{
			$domain = getGet('domain', '');
			mysql_query(sprintf("DELETE FROM blocd_domains WHERE Domain='%s'", addslashes($domain)));
			termination();
			return;
		}
	}


	//-- Homepage
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title>Blocd</title>
	<link rel="stylesheet" type="text/css" href="styles.css" media="all" />
	<script>
		function doAction(pAction)
		{
			// Special parameters
			p = '';
			switch (pAction)
			{
				case 'import':
					p += confirm('Click on OK to load the domains as validated, else you will have to review them manually.');
					break;
			}

			// Processing
			url = 'index.php?action=' + pAction;
			if (p != '')
				url += '&p=' + p;
			window.location = url;
			return false;
		}

		function doValidate(pDomain, pValidation, pUIID)
		{
			// Special actions
			if ((pDomain == '') || (!pValidation && !confirm('Do you really want to delete "'+pDomain+'" ?')))
				return true;

			// Processing
			var ajax = new XMLHttpRequest();
			if (pUIID > 0)
			{
				ajax.onreadystatechange = function() {
					if ((this.readyState == 4) && (this.status == 200))
						document.getElementById('entry_'+pUIID).style.display = 'none';
				};
			}
			ajax.open('GET', 'index.php?action=bg_'+(pValidation?'ok':'ko')+'&domain='+encodeURI(pDomain), true);
			ajax.send();
			return false;
		}

		function doWhois(pDomain)
		{
			var dlg;
			if (pDomain == '')
				return false;
			dlg = window.open('http://www.whois-raynette.fr/whois/'+pDomain, '_blank');
			dlg.focus();
			return false;
		}
	</script>
</head>
<body>
	<h1>Blocd - Manage your spams</h1>

	<h2>Actions</h2>
	<table>
		<tr>
			<td>General</td>
			<td>
				<input class="blocd_button" type="button" value="Home" onClick="doAction('')" />
				<input class="blocd_button" type="button" value="Maintain DB" onClick="doAction('maintenance')" />
			</td>
		</tr>
		<tr>
			<td>Git management</td>
			<td>
				<input class="blocd_button" type="button" value="git pull" onClick="doAction('pull')" />
				<input class="blocd_button" type="button" value="Import locally after a git pull" onClick="doAction('import')" />
				<input class="blocd_button" type="button" value="Contribute to the project" onClick="doAction('contribute')" />
			</td>
		</tr>
		<tr>
			<td>Mailbox</td>
			<td>
				<input class="blocd_button" type="button" value="Configure the accounts" onClick="doAction('configure')" />
				<input class="blocd_button" type="button" value="Enumerate the folders" onClick="doAction('enumerate')" />
				<input class="blocd_button" type="button" value="Read the mailbox" onClick="doAction('fetch')" />
				<input class="blocd_button" type="button" value="Clean the mailbox" onClick="doAction('clean')" />
			</td>
		</tr>
		<tr>
			<td>Domain control</td>
			<td>
				<input class="blocd_button" type="button" value="Validate" onClick="doAction('validate')" />
				<input class="blocd_button" type="button" value="Show" onClick="doAction('show')" />
			</td>
		</tr>
		<tr>
			<td>File management</td>
			<td>
				<input class="blocd_button" type="button" value="Generate the files" onClick="doAction('generate')" />
			</td>
		</tr>
	</table>

	<?php
		switch ($action)
		{
			//-- Database maintenance
			case 'maintenance':
			{
				mysql_query('ALTER TABLE blocd_domains ORDER BY Domain');
				mysql_query('OPTIMIZE TABLE blocd_domains');
				mysql_query('ALTER TABLE blocd_mbox ORDER BY Email');
				mysql_query('OPTIMIZE TABLE blocd_mbox');
				echo '<p>The database has been optimized.</p>';
				break;
			}

			//-- Git pull
			case 'pull':
			{
				echo '<p>Execution log :</p><pre>';
				exec('git pull', $data);
				foreach ($data as $line)
					echo htmlspecialchars($line), "\n";
				echo '</pre>';
				unset($line, $data);
				break;
			}

			//-- Importer
			case 'import':
			{
				// Parameter
				$param = getGet('p', '');
				$param = in_array($param, array('', 'true', 'True', '1', 'ok')) ? 'X' : '';

				// Processing
				echo '<ul>';
				$content = explode("\n", file_get_contents(FILE_RAW));
				foreach ($content as $data)
				{
					$data = trim($data);
					if ($data != '')
					{
						echo sprintf('<li>%s</li>', $data);
						mysql_query(sprintf("INSERT IGNORE INTO blocd_domains (Domain, OK) VALUES ('%s', '%s')", addslashes($data), addslashes($param)));
					}
				}
				echo '</ul>';
				unset($data, $content, $param);
				break;
			}

			//-- Contribute
			case 'contribute':
			{
				echo '	<h2>How to contribute</h2>
						<ol>
							<li>Pull the Git repository to get the latest update</li>
							<li>Synchronize your local database</li>
							<li>Fetch your spams</li>
							<li>Classify the reported domains <ins>carefully</ins> !</li>
							<li>Generate the files</li>
							<li>Commit your changes into a new branch</li>
							<li>Create a pull request on Github</li>
						</ol>';

				// Show the Git diff
				if (is_dir('.git'))
				{
					exec('git diff', $data);
					echo '<h2>Git diff</h2><pre>';
					foreach ($data as $line)
						echo htmlspecialchars($line), "\n";
					echo '</pre>';
				}
				unset($line, $data);
				break;
			}

			//-- Manage the mailboxes
			case 'configure':
			{
				// Update
				if (isset($_POST['blocd_userform_submit']))
				{
					$email = getPost('blocd_userform_email', getPost('blocd_userform_email_new', ''));
					if ($email != '')
					{
						$host = getPost('blocd_userform_host', '');
						$password = getPost('blocd_userform_password', '');
						$folder = getPost('blocd_userform_folder', '');
						$enabled = (isset($_POST['blocd_userform_enabled']) ? 'X' : '');
						if (isset($_POST['blocd_userform_delete']))
							mysql_query(sprintf("DELETE FROM blocd_mbox WHERE Email='%s'", addslashes($email)));
						else
						{
							mysql_query(sprintf("INSERT IGNORE INTO blocd_mbox VALUES ('%s', '%s', '%s', '%s', '%s')"
													, addslashes($email)
													, addslashes($host)
													, addslashes($password)
													, addslashes($folder)
													, addslashes($enabled)
												));
							if (mysql_affected_rows() == 0)
							{
								$fields = array('Host'     => $host,
												'Password' => $password,
												'Folder'   => $folder,
												'Enabled'  => $enabled);
								foreach ($fields as $key => $val)
									if (($key == 'Enabled') || ($val != ''))
										mysql_query(sprintf("UPDATE blocd_mbox SET %s='%s' WHERE Email='%s'"
																, addslashes($key)
																, addslashes($val)
																, addslashes($email)
															));
							}
						}
					}
					unset($val, $key, $fields, $enabled, $folder, $password, $host, $email);
				}

				// Form
				echo '	<h2>Account management</h2>
						<form action="index.php?action=configure" method="post">
							<table>
								<tr>
									<td>Email</td>
									<td><select name="blocd_userform_email">
										<option value="">-- New mailbox --</option>';
				$allData = mysql_query('SELECT Email FROM blocd_mbox ORDER BY Email');
				while ($data = mysql_fetch_array($allData))
					echo sprintf('<option value="%s">%s</option>', htmlspecialchars($data['Email']), htmlspecialchars($data['Email']));
				echo '					</select>
										<input type="text" name="blocd_userform_email_new" /></td>
								</tr>
								<tr>
									<td>Host</td>
									<td><input type="text" name="blocd_userform_host" /> by IMAP protocol</td>
								</tr>
								<tr>
									<td>Password</td>
									<td><input type="password" name="blocd_userform_password" /></td>
								</tr>
								<tr>
									<td>Folder</td>
									<td><input type="text" name="blocd_userform_folder" /></td>
								</tr>
								<tr>
									<td>Enabled</td>
									<td><input type="checkbox" name="blocd_userform_enabled" checked="checked" /></td>
								</tr>
								<tr>
									<td>Delete</td>
									<td><input type="checkbox" name="blocd_userform_delete" /></td>
								</tr>
								<tr>
									<td>&nbsp;</td>
									<td><input type="submit" name="blocd_userform_submit" /></td>
								</tr>
							</table>
						</form>';
				unset($data, $allData);
				break;
			}

			//-- Enumerate the folders to find the right folder to extract
			case 'enumerate':
			{
				$allData = mysql_query("SELECT * FROM blocd_mbox WHERE Enabled='X'");
				while ($data = mysql_fetch_array($allData))
				{
					// Open
					$data['Host'] = sprintf('{%s}', $data['Host']);
					$mbox = imap_open($data['Host'], $data['Email'], $data['Password'], OP_HALFOPEN);
					if ($mbox === false)
						continue;

					// List
					$list = imap_list($mbox, $data['Host'], '*');
					echo sprintf('<h3>%s</h3><ul>', htmlspecialchars($data['Email']));
					if (!is_array($list))
						echo '<li>', imap_last_error(), '</li>';
					else
						foreach ($list as $entry)
							echo '<li>', imap_utf7_decode($entry), '</li>';
					echo '</ul>';
					imap_close($mbox);
				}
				unset($entry, $list, $mbox, $data, $allData);
				break;
			}

			//-- Save the senders into the database
			case 'fetch':
			{
				$buffer = array();
				$allData = mysql_query("SELECT * FROM blocd_mbox WHERE Enabled='X'");
				while ($data = mysql_fetch_array($allData))
				{
					// Open
					$mbox = imap_open(sprintf('{%s}%s', $data['Host'], $data['Folder']), $data['Email'], $data['Password']);
					if ($mbox === false)
						continue;

					// List
					$loopMax = imap_num_msg($mbox);
					echo sprintf('<h3>%s</h3><ul>', htmlspecialchars($data['Email']));
					for ($loop=1 ; $loop<=$loopMax; $loop++)
					{
						$mail = imap_headerinfo($mbox, $loop);
						if ($mail === False)
							break;

						// $mail->from[0]->mailbox
						// $mail->from[0]->host
						// $mail->reply_to[0]->mailbox
						// $mail->reply_to[0]->host
						$domain = getDomain($mail->from[0]->host);
						if (($domain != '') && !in_array($domain, $buffer))
						{
							$buffer[] = $domain;
							echo sprintf('<li>%s</li>', $domain);
							mysql_query(sprintf("INSERT IGNORE INTO blocd_domains (Domain) VALUES ('%s')", addslashes($domain)));
						}
						if ($loop % 10 == 0)
							flush();
					}
					echo '</ul>';
					imap_close($mbox);
				}
				unset($domain, $mail, $loop, $loopMax, $mbox, $data, $allData, $buffer);
				break;
			}

			//-- Clean the mailbox
			case 'clean':
			{
				echo '<p>Please clean your mailbox yourself, so that you do not reparse the spams again.</p>';
				break;
			}

			//-- Validate the loaded domains
			case 'validate':
			{
				echo '<ul>';
				$allData = mysql_query("SELECT * FROM blocd_domains WHERE OK='' ORDER BY Domain");
				$counter = 1;
				while ($data = mysql_fetch_array($allData))
				{
					echo sprintf('	<li id="entry_%d">
										<a href="#" title="Accept" onClick="doValidate(\'%s\',true,%d)">&#x2705;</a>
										<a href="#" title="Reject" onClick="doValidate(\'%s\',false,%d)">&#x274C;</a>
										<a href="#" title="Whois" onClick="doWhois(\'%s\')">&#x1F4EC;</a>
										%s
									</li>%s'
										, $counter
										, addslashes($data['Domain'])
										, $counter
										, addslashes($data['Domain'])
										, $counter
										, addslashes($data['Domain'])
										, htmlspecialchars($data['Domain'])
										, "\n"
								);
					$counter++;
				}
				if ($counter <= 1)
					echo '<li>You have no pending validation. Maybe is it time for you to <a href="#" onClick="doAction(\'contribute\')">contribute</a> ?</li>';
				echo '</ul>';
				unset($data, $counter, $allData);
				break;
			}

			//-- Show the domains
			case 'show':
			{
				echo '<ul>';
				$allData = mysql_query("SELECT * FROM blocd_domains ORDER BY Domain");
				$counter = 1;
				while ($data = mysql_fetch_array($allData))
				{
					echo sprintf('<li id="entry_%d">
									<a href="#" title="Remove" onClick="doValidate(\'%s\',false,%d)">&#x274C;</a>
									<a href="#" title="Whois" onClick="doWhois(\'%s\')">&#x1F4EC;</a>
									%s%s</li>'
										, $counter
										, addslashes($data['Domain'])
										, $counter
										, addslashes($data['Domain'])
										, htmlspecialchars($data['Domain'])
										, ($data['OK']!='X'?' <em style="color:red">(Pending)</em>':'')
								);
					$counter++;
				}
				unset($counter, $data, $allData);
				echo '</ul>';
				break;
			}

			//-- Generate the lists
			case 'generate':
			{
				$fileRaw = '';
				$filePF = '';
				$allData = mysql_query("SELECT * FROM blocd_domains WHERE OK='X' ORDER BY Domain");
				while ($data = mysql_fetch_array($allData))
				{
					$fileRaw .= sprintf("%s\n", $data['Domain']);
					$filePF .= sprintf("/%s$/i REJECT\n", str_replace('.', '\\.', $data['Domain']));
				}
				_file_put_contents(FILE_RAW, $fileRaw);
				_file_put_contents(FILE_POSTFIX, $filePF);
				echo '<p>The files are generated successfully.</p>';
				unset($data, $allData, $filePF, $fileRaw);
				break;
			}
		}
	?>

	<p id="footer">Powered by <em>Block list of commercial domains</em> (Blocd) available at <img src="github.png" style="width:16px; height:16px" alt=""/> <a href="https://github.com/ecrucru/blocd/">https://github.com/ecrucru/blocd/</a></p>
</body>
</html>
<?php
	termination();
?>