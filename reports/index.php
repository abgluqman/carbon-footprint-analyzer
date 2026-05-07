<?php
// If someone tries to browse the reports folder directly
// send them back to the dashboard
header("Location: ../pages/dashboard.php");
exit();
?>
```