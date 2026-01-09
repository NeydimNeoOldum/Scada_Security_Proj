<?php
session_start();
require 'includes/tab_session.php';

// Only destroy this tab's session, not the entire session
destroy_tab_session();

header("Location: index.php");
exit;
?>