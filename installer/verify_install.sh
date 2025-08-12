#!/bin/bash
# MusicFLAC Installation Verification Script

echo "=== MusicFLAC Installation Verification ==="
echo ""

# Check PHP version
echo "Checking PHP version..."
php -v | head -n 1
echo ""

# Check required PHP extensions
echo "Checking required PHP extensions..."
extensions=("pdo_sqlite" "curl" "zip" "openssl")
missing=()

for ext in "${extensions[@]}"; do
    if php -m | grep -q "$ext"; then
        echo "✓ $ext is installed"
    else
        echo "✗ $ext is missing"
        missing+=("$ext")
    fi
done

echo ""

# Check directory permissions
echo "Checking directory permissions..."
directories=("data" "downloads")

for dir in "${directories[@]}"; do
    if [ -d "$dir" ]; then
        if [ -w "$dir" ]; then
            echo "✓ $dir is writable"
        else
            echo "✗ $dir is not writable"
        fi
    else
        echo "✗ $dir directory not found"
    fi
done

echo ""

# Check installer files
echo "Checking installer files..."
installer_files=("installer/install.php" "installer/index.php" "installer/README.md")

for file in "${installer_files[@]}"; do
    if [ -f "$file" ]; then
        echo "✓ $file exists"
    else
        echo "✗ $file not found"
    fi
done

echo ""

# Summary
if [ ${#missing[@]} -eq 0 ]; then
    echo "✓ All required PHP extensions are installed"
else
    echo "✗ Missing PHP extensions: ${missing[*]}"
    echo "Please install the missing extensions and run this script again"
fi

echo ""
echo "=== Verification Complete ==="