# Checks the Windows Setup.exe and update package are Authenticode signed and every Argo Books DLL and EXE is the release version.
#
# Usage: verify_windows_release.ps1 -UpdatesFolder resources/updates [-BaseUrl https://argorobots.com]

param(
    [Parameter(Mandatory)] [string] $UpdatesFolder,
    [string] $BaseUrl = 'https://argorobots.com'
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$userAgent = 'ArgoBooks-release-verifier (GitHub Actions)'

$release = Get-Content -Raw (Join-Path $UpdatesFolder 'release.json') | ConvertFrom-Json
$version = $release.version
$feed = Get-Content -Raw (Join-Path $UpdatesFolder 'releases.win.json') | ConvertFrom-Json
$package = $feed.Assets | Where-Object { $_.Type -eq 'Full' -and $_.Version -eq $version } | Select-Object -First 1
if (-not $package) { Write-Host "::error::releases.win.json has no full package for $version"; exit 1 }

$work = Join-Path ([IO.Path]::GetTempPath()) "argo-windows-$version"
Remove-Item -Recurse -Force $work -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Force "$work\package" | Out-Null

$failed = $false

function Test-Signature([string] $path, [string] $label) {
    # Without a valid Authenticode signature SmartScreen warns every user who runs it.
    $authenticode = Get-AuthenticodeSignature $path
    $signer = $authenticode.SignerCertificate.Subject
    if ($authenticode.Status -ne 'Valid') {
        Write-Host "::error::$label Authenticode signature is $($authenticode.Status): $($authenticode.StatusMessage)"
        return $false
    }
    if ($signer -notmatch '^CN=Evan Di Placido,') {
        Write-Host "::error::$label is signed by '$signer', expected Evan Di Placido"
        return $false
    }
    if (-not $authenticode.TimeStamperCertificate) {
        Write-Host "::error::$label signature has no timestamp, so it stops validating when the certificate expires"
        return $false
    }
    Write-Host "$label Authenticode OK: $signer"
    return $true
}

$setupName = "ArgoBooks-$version-win-Setup.exe"
$setup = "$work\$setupName"
Invoke-WebRequest "$BaseUrl/resources/downloads/$version/$setupName" -OutFile $setup -UserAgent $userAgent
if (-not (Test-Signature $setup 'Setup.exe')) { $failed = $true }

$nupkg = "$work\package.zip"
Invoke-WebRequest "$BaseUrl/resources/updates/$($package.FileName)" -OutFile $nupkg -UserAgent $userAgent
Expand-Archive $nupkg -DestinationPath "$work\package"
$app = "$work\package\lib\app"

foreach ($required in 'ArgoBooks.Core.dll', 'ArgoBooks.dll', 'ArgoBooks.Shared.dll', 'Argo Books.dll', 'Argo Books.exe', 'Squirrel.exe') {
    if (-not (Test-Path -LiteralPath (Join-Path $app $required))) {
        Write-Host "::error::$required is not in the update package"
        $failed = $true
    }
}

# Squirrel.exe becomes Update.exe once installed, and the stub is what the shortcuts start.
foreach ($exe in 'Argo Books.exe', 'Argo Books_ExecutionStub.exe', 'Squirrel.exe') {
    $path = Join-Path $app $exe
    if ((Test-Path -LiteralPath $path) -and -not (Test-Signature $path $exe)) { $failed = $true }
}

$expected = [version] $version
foreach ($file in Get-ChildItem $app -File | Where-Object { $_.Name -match '^(ArgoBooks|Argo Books)[^_]*\.(dll|exe)$' }) {
    $raw = $file.VersionInfo
    $actual = [version]::new($raw.FileMajorPart, $raw.FileMinorPart, $raw.FileBuildPart)
    if ($actual -eq $expected) {
        Write-Host "$($file.Name): $actual"
    } else {
        Write-Host "::error::$($file.Name) is version $actual, expected $version"
        $failed = $true
    }
}

Remove-Item -Recurse -Force $work -ErrorAction SilentlyContinue
if ($failed) { exit 1 }
Write-Host "Setup.exe and every Argo Books binary in the $version update package check out"
