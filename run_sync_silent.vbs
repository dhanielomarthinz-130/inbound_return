' IEG Sync Worker Silent Runner (Sekali Jalan)
Set fso = CreateObject("Scripting.FileSystemObject")
currentDir = fso.GetParentFolderName(WScript.ScriptFullName)

phpBin = "C:\xampp\php\php.exe"
If Not fso.FileExists(phpBin) Then
    If fso.FileExists("C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe") Then
        phpBin = "C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe"
    Else
        phpBin = "php.exe"
    End If
End If

Set WshShell = CreateObject("WScript.Shell")
WshShell.CurrentDirectory = currentDir
WshShell.Run """" & phpBin & """ sync_worker.php", 0, True
