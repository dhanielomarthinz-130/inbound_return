' IEG Sync Worker Silent Runner (Sekali Jalan)
Set fso = CreateObject("Scripting.FileSystemObject")
currentDir = fso.GetParentFolderName(WScript.ScriptFullName)

phpBin = "php.exe"
If fso.FileExists("C:\xampp\php\php.exe") Then
    phpBin = "C:\xampp\php\php.exe"
Else
    laragonPhpDir = "C:\laragon\bin\php"
    If fso.FolderExists(laragonPhpDir) Then
        Set parentFld = fso.GetFolder(laragonPhpDir)
        For Each subFld in parentFld.SubFolders
            candidate = subFld.Path & "\php.exe"
            If fso.FileExists(candidate) Then
                phpBin = candidate
                Exit For
            End If
        Next
    End If
End If

Set WshShell = CreateObject("WScript.Shell")
WshShell.CurrentDirectory = currentDir
WshShell.Run """" & phpBin & """ sync_worker.php", 0, True
