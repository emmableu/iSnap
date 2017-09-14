<!doctype html>

<html>

	<head>
		<meta charset="UTF-8">
		<title>View Project</title>
		<link rel="stylesheet" type="text/css" href="../../../logging/view/table.css">
		<style>
			html {
				height: 100%;
			}
			body {
				height: calc(100% - 20px);
			}
			/* Credit: http://stackoverflow.com/questions/5645986/two-column-div-layout-with-fluid-left-and-fixed-right-column */
			#wrapper {
				height: 100%;
			}
			#content {
				float: right;
				width: 550px;
				display: block;
				height: 100%;
			}
			#sidebar {
				width: calc(100% - 560px);
				height: 100%;
				float: left;
			}
			#cleared {
				clear: both;
			}
			a.disabled:link {
				pointer-events: none;
				color: #666;
				font-weight: normal;
				font-style: italic;
			}
		</style>
		<script type="text/javascript">
			var user = "<?php echo $_GET['user']; ?>";

			function loadSnap(id, project, assignment, callback) {
				var xhr = new XMLHttpRequest();
				xhr.onreadystatechange = function() {
					if (xhr.readyState==4 && xhr.status==200) {
						var contentWindow = document.getElementById('snap').contentWindow;
						contentWindow.Assignment.setID(assignment);
						contentWindow.ide.droppedText(xhr.responseText);
						loadHintTable(id);
						if (callback) callback();
					}
				};
				xhr.open("GET", "../../../logging/view/code.php?id=" + id + "&project=" + project, true);
				xhr.send();
				window.location.hash = id;
				window.index = rows.findIndex(function(a) {
					return a.dataset.rid == id;
				});
			}

			function loadHintTable(id) {
				var xhr = new XMLHttpRequest();
				xhr.onreadystatechange = function() {
					if (xhr.readyState==4 && xhr.status==200) {
						var logTable = document.getElementById('logTable');
						// Clean the newly added table
						var next = logTable.nextSibling;
						while (next) {
							next.remove();
							next = logTable.nextSibling;;
						}

						logTable.insertAdjacentHTML('afterend', xhr.responseText);
					}
				};
				xhr.open("GET", "handmade-hintTable.php?user=" + user + "&logID=" + id, true);
				xhr.send();
			}

			function addHint(rowID, projectID, assignment) {
				var contentWindow = document.getElementById('snap').contentWindow;
				if (contentWindow.ide.stage.guid !== projectID) {
					alert("Project ID does not match original code. Make sure you pressed the right save button.");
					return;
				}
				var xhr = new XMLHttpRequest();
				xhr.onreadystatechange = function() {
					if (xhr.readyState==4 && xhr.status==200) {
						addNewHintRow(xhr.responseText, projectID, assignment);
					}
				};
				xhr.open("POST", "handmade-hint.php?rowID=" + rowID + "&user=" + user, true);
				xhr.send();
			}

			function addNewHintRow(hintID, projectID, assignment) {
				var hintTable = document.getElementById("hintTable");
				var row = hintTable.insertRow();
				row.id = "r"+hintID;
				var viewCell = row.insertCell(0);
				viewCell.innerHTML = "<i>Edits</i>";

				var hintIDCell = row.insertCell(1);
				hintIDCell.innerHTML = hintID + "<br /><button onclick='deleteHint(" + hintID + ")'>Delete</button>";

				var hintCell = row.insertCell(2);
				var load = "<a id='l" + hintID + "' class='disabled' href='javascript:void(0)' onclick='loadHint(" + hintID + ",\"" + assignment + "\")'>Load</a>";
				var save = "<a href='javascript:void(0)' onclick='saveHint(" + hintID + ",\"" + projectID + "\")'>Save</a>";
				hintCell.innerHTML = "<span id='d" + hintID + "'><i>No hint saved</i></span><br/>" + load + "<br/><br />" + save;

				var priorityCell = row.insertCell(3);
				priorityCell.innerHTML = "<input id='p" + hintID +"' type='text' value=''>";
			}

			function deleteHint(hintID) {
				if (document.getElementById("hintTable").rows.length <= 2) {
					alert("You are not allowed to delete the last row.");
					return;
				}
				var xhr = new XMLHttpRequest();
				xhr.onreadystatechange = function() {
					if (xhr.readyState==4 && xhr.status==200) {
						var hintRow = document.getElementById("r" + hintID);
						hintRow.parentNode.removeChild(hintRow);
					}
				};
				var priority = document.getElementById('p' + hintID).value;
				xhr.open("DELETE", "handmade-hint.php?hintID=" + hintID, true);
				xhr.send();
			}

			function saveHint(hintID, projectID) {
				var contentWindow = document.getElementById('snap').contentWindow;
				if (contentWindow.ide.stage.guid !== projectID) {
					alert("Project ID does not match original code. Make sure you pressed the right save button.");
					return;
				}
				var code = contentWindow.Trace.lastCode;
				if (!code || code.length == 0) alert("No code to save");
				var xhr = new XMLHttpRequest();
				xhr.onreadystatechange = function() {
					if (xhr.readyState==4 && xhr.status==200) {
						var date = document.getElementById('d' + hintID);
						date.innerHTML = xhr.responseText;
						var load = document.getElementById('l' + hintID);
						load.classList.remove('disabled');
					}
				};
				var priority = document.getElementById('p' + hintID).value;
				xhr.open("PUT", "handmade-hint.php?hintID=" + hintID + "&priority=" + priority, true);
				xhr.send(code);
			}

			function loadHint(hintID, assignment) {
				var xhr = new XMLHttpRequest();
				xhr.onreadystatechange = function() {
					if (xhr.readyState==4 && xhr.status==200) {
						var contentWindow = document.getElementById('snap').contentWindow;
						contentWindow.Assignment.setID(assignment);
						contentWindow.ide.droppedText(xhr.responseText);
					}
				};
				xhr.open("GET", "handmade-hint.php?hintID=" + hintID, true);
				xhr.send();
			}

			function showEdits(edits, id, project, assignment, callback) {
				var contentWindow = document.getElementById('snap').contentWindow;
				var provider = contentWindow.hintProvider;
				if (!provider) return;
				provider.forcedHints = [];
				loadSnap(id, project, assignment, function() {
					setTimeout(function() {
						provider.forcedHints = edits;
						provider.displays.forEach(function(display) {
							display.enabled = true;
						});
						provider.getHintsFromServer();
					}, 300);
				});
			}

			function toggleLogTable() {
				var logTable = document.getElementById('logTable');
				if (logTable.style.display === "block") {
					logTable.style.display = "none";
				} else {
					logTable.style.display = "block";
				}
			}

		</script>
	</head>

	<body>
		<div id="wrapper">
			<div id="sidebar">
				 <iframe id="snap" width="100%" height="100%" src="../../../snap.html?assignment=view&hints=true"></iframe>
			</div>
			<div id="content">
				<div style="overflow: scroll; height: 100%;">
				<?php
include '../../../logging/config.php';
if ($enable_viewer) {
	$mysqli = new mysqli($host, $user, $password, $db);
	if ($mysqli->connect_errno) {
		die ("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
	}

	$user = $mysqli->escape_string($_GET['user']);

	// Shows all the available logs which request hint.
	$query = "SELECT DISTINCT rowID, assignmentID, projectID
	FROM handmade_hints JOIN trace ON handmade_hints.rowID=trace.id
	WHERE handmade_hints.userID='$user' ORDER BY rowID";

	$logIDs = $mysqli->query($query);
	echo "<button onclick='toggleLogTable()'>Toggle Log Table</button>";
	echo "<table id='logTable' cellspacing='0' style='display:block'>";
	echo "<thead><th>Log ID</th><th>Project ID</th><th>Log ID</th><th>Project ID</th><th>Log ID</th><th>Project ID</th></thead>";
	$cnt = 0;
	while($row = mysqli_fetch_array($logIDs)) {
		$id=$row['rowID'];
		$assignmentID = $row["assignmentID"];
		$projectID = $row["projectID"];
		$displayID = substr($projectID, 0, strpos($projectID, '-'));
		$onclick = "loadSnap(\"$id\", \"$projectID\", \"$assignmentID\")";
		$onclick = htmlspecialchars($onclick);
		$contextLink = "../../../logging/view/display.php?id=$projectID&assignment=$assignmentID#$id";
		if ($cnt%3 == 0) {
			echo "<tr>";
		}
		echo "
			<td id='$id'>
				<a class='rlink' data-rid='$id' href='#' onclick=\"$onclick\">$id</a>
			</td>
			<td>$assignmentID </br>
				<a href='$contextLink' target='_blank' title='See the full logs for this attempt...'>$displayID</a></td>";
		if (($cnt+1)%3 == 0) {
			echo "</tr>";
		}
		$cnt = $cnt + 1;
	}
	echo "</table>";
} else {
	echo "You do not have permission to view this page";
}
				?>
				</div>
			</div>
			<div id="cleared"></div>
			<script type = "text/javascript">
				var rows = [].slice.call(
					document.getElementsByClassName("rlink"));
				var index = 0;
				document.addEventListener('keypress', function(event) {
					var code = event.which || event.keyCode;
					if (code === 100 && index < rows.length - 1) {
						rows[++index].onclick();
					} else if (code === 97 && index > 0) {
						rows[--index].onclick();
					}
				});
				var hash = parseInt(window.location.hash.replace("#", ""));
				if (!isNaN(hash)) {
					index = rows.findIndex(function(a) {
						return a.dataset.rid == hash;
					});
				}
				var snap = document.getElementById("snap");
				snap.onload = function() {
					var cw = snap.contentWindow;
					cw.ide.toggleStageSize();
					cw.hintProvider.forcedHints = [];
					if (!cw.hintProvider.displays.some(function(display) {
						return display instanceof cw.DebugDisplay;
					})) {
						var debugDisplay = new cw.DebugDisplay();
						console.log(debugDisplay);
						cw.hintProvider.displays.push(debugDisplay);
						debugDisplay.show();
					}
					cw.hintProvider.displays.forEach(function(display) {
						if (display instanceof cw.HighlightDisplay) {
							display.showInserts = true;
							display.forceShowDialog = true;
							display.enabled = true;
						}
					});
					if (rows.length > 0) {
						rows[index].onclick();
					}
				}
			</script>
		</div>
	</body>
</html>