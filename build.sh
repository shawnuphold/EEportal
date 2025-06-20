#!/bin/bash

echo "🚀 Starting plugin build..."

# Show what files we have
echo "📁 Files in repository:"
ls -la

# Create build directory
mkdir -p build
cd build

echo "📦 Creating plugin directory..."
mkdir -p employee-portal

echo "📋 Copying files..."

# Copy files one by one to see what works
if [ -f "../employee-portal.php" ]; then
    cp ../employee-portal.php employee-portal/
    echo "✅ Copied employee-portal.php"
else
    echo "❌ employee-portal.php not found"
fi

if [ -f "../readme.txt" ]; then
    cp ../readme.txt employee-portal/
    echo "✅ Copied readme.txt"
else
    echo "❌ readme.txt not found"
fi

if [ -d "../includes" ]; then
    cp -r ../includes employee-portal/
    echo "✅ Copied includes folder"
else
    echo "❌ includes folder not found"
fi

if [ -d "../admin" ]; then
    cp -r ../admin employee-portal/
    echo "✅ Copied admin folder"
else
    echo "❌ admin folder not found"
fi

if [ -d "../public" ]; then
    cp -r ../public employee-portal/
    echo "✅ Copied public folder"
else
    echo "❌ public folder not found"
fi

if [ -d "../templates" ]; then
    cp -r ../templates employee-portal/
    echo "✅ Copied templates folder"
else
    echo "❌ templates folder not found"
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
