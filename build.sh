#!/bin/bash

echo "🚀 Starting plugin build..."

# Show what files we have
echo "📁 Files in repository root:"
ls -la

echo "📁 Files in employee-portal folder:"
ls -la employee-portal/

# Create build directory
mkdir -p build
cd build

echo "📦 Creating plugin directory..."
mkdir -p employee-portal

echo "📋 Copying files from employee-portal folder..."

# Copy files from the employee-portal source folder
if [ -f "../employee-portal/employee-portal.php" ]; then
    cp ../employee-portal/employee-portal.php employee-portal/
    echo "✅ Copied employee-portal.php"
else
    echo "❌ employee-portal.php not found"
fi

if [ -f "../employee-portal/readme.txt" ]; then
    cp ../employee-portal/readme.txt employee-portal/
    echo "✅ Copied readme.txt"
else
    echo "❌ readme.txt not found"
fi

if [ -d "../employee-portal/includes" ]; then
    cp -r ../employee-portal/includes employee-portal/
    echo "✅ Copied includes folder"
else
    echo "❌ includes folder not found"
fi

if [ -d "../employee-portal/admin" ]; then
    cp -r ../employee-portal/admin employee-portal/
    echo "✅ Copied admin folder"
else
    echo "❌ admin folder not found"
fi

if [ -d "../employee-portal/public" ]; then
    cp -r ../employee-portal/public employee-portal/
    echo "✅ Copied public folder"
else
    echo "❌ public folder not found"
fi

if [ -d "../employee-portal/templates" ]; then
    cp -r ../employee-portal/templates employee-portal/
    echo "✅ Copied templates folder"
else
    echo "❌ templates folder not found"
fi

# Copy any other files that might exist
if [ -f "../employee-portal/LICENSE" ]; then
    cp ../employee-portal/LICENSE employee-portal/
    echo "✅ Copied LICENSE"
fi

if [ -f "../employee-portal/CHANGELOG.md" ]; then
    cp ../employee-portal/CHANGELOG.md employee-portal/
    echo "✅ Copied CHANGELOG.md"
fi

echo "📋 Files in plugin directory:"
ls -la employee-portal/

echo "📦 Creating ZIP file..."
zip -r employee-portal.zip employee-portal/

echo "📊 ZIP file info:"
ls -lh employee-portal.zip

echo "📋 Testing ZIP contents:"
unzip -l employee-portal.zip

# Copy to publish directory
cp employee-portal.zip ../
cd ..

echo "✅ Build completed!"
echo "📁 Final files:"
ls -la
