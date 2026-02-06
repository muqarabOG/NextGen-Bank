# Git Push Automation Script
$repoUrl = "https://github.com/muqarabOG/NextGen-Bank.git"

Write-Host "🚀 Starting Git Push Sequence..."

# Initialize Git if not exists
if (!(Test-Path .git)) {
    Write-Host "Initializing Git..."
    git init
}

# Add all files
Write-Host "Adding files..."
git add .

# Commit
Write-Host "Committing..."
git commit -m "Final Release: NextGen Bank System (Full Stack + AI)"

# Branch rename
git branch -M main

# Add Remote (remove if exists to be safe)
git remote remove origin 2>$null
git remote add origin $repoUrl

# Push
Write-Host "Pushing to GitHub ($repoUrl)..."
git push -u origin main

Write-Host "✅ Done! Project is live on GitHub."
