Set WshShell = CreateObject("WScript.Shell")
WshShell.CurrentDirectory = "C:\xampp\htdocs\retrun.inboud"
WshShell.Run """C:\xampp\php\php.exe"" sync_worker.php --daemon", 0, False
