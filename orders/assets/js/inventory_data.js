/**
 * Global Inventory Data for IQA Metal
 * Separated by sector for cleaner management.
 */

var IQA_LaptopInventory = {
    "Dell": {
        models: ["Latitude", "Precision", "Vostro", "XPS"],
        series: ["7420", "5420", "5520", "7390", "7400", "7410", "5410", "5510", "5550", "3551", "XPS 13", "XPS 15"]
    },
    "HP": {
        models: ["EliteBook", "ProBook", "ZBook", "Pavilion", "Envy", "Spectre"],
        series: ["840 G8", "840 G7", "830 G7", "850 G8", "450 G8", "440 G8", "650 G8", "Studio G7", "Firefly 15", "Power G7"]
    },
    "Lenovo": {
        models: ["ThinkPad", "IdeaPad", "Yoga"],
        series: ["T14 Gen 2", "T14s", "T490", "T480", "X1 Carbon Gen 9", "X1 Yoga Gen 6", "L14", "P15 Gen 2", "P1 Gen 4", "IdeaPad 5"]
    },
    "Apple": {
        models: ["MacBook Pro", "MacBook Air"],
        series: ["M1 13-inch", "M1 Pro 14-inch", "M1 Max 16-inch", "M2 Air", "M2 Pro 14-inch", "M2 Max 16-inch", "Intel i7 13-inch", "Intel i9 16-inch"]
    },
    "Microsoft": {
        models: ["Surface Laptop", "Surface Book", "Surface Laptop Go", "Surface Laptop Studio"],
        series: ["Laptop 4", "Laptop 3", "Laptop 5", "Book 3", "Laptop Studio"]
    },
    "Samsung": {
        models: ["Galaxy Book", "Galaxy Book Pro", "Galaxy Book Odyssey"],
        series: ["Book Pro", "Book Pro 360", "Book Ultra", "Book 3", "Book 2"]
    },
    "Asus": {
        models: ["ZenBook", "VivoBook", "ROG Laptop", "ExpertBook"],
        series: ["UX425", "S15", "Zephyrus G14", "Flow X13", "B9450"]
    },
    "Acer": {
        models: ["Swift", "Spin", "Aspire", "TravelMate"],
        series: ["Swift 3", "Spin 5", "Aspire 5", "P6", "X5"]
    },
    "MSI": {
        models: ["Prestige", "Summit", "Stealth", "Creator"],
    }
};

var IQA_DesktopInventory = {
    "Dell": {
        models: ["OptiPlex", "Precision Tower", "Vostro Desktop", "Inspiron Desktop"],
        series: ["7040 SFF", "7050 SFF", "7060 SFF", "7070 SFF", "5040 SFF", "5050 SFF", "3040 SFF", "3050 SFF", "7040 Micro", "7050 Micro", "3050 Micro", "Tower"]
    },
    "HP": {
        models: ["EliteDesk", "ProDesk", "Workstation", "Pavilion Desktop"],
        series: ["800 G2 SFF", "800 G3 SFF", "800 G4 SFF", "600 G2 SFF", "600 G3 SFF", "400 G3 SFF", "800 G2 Mini", "800 G3 Mini", "Z240 Tower", "Z440 Tower"]
    },
    "Lenovo": {
        models: ["ThinkCentre", "ThinkStation", "IdeaCentre"],
        series: ["M900 SFF", "M700 SFF", "M910s SFF", "M920s SFF", "M710q Tiny", "M910q Tiny", "M920q Tiny", "P310 Tower", "P320 Tower"]
    },
    "Apple": {
        models: ["Mac Mini", "iMac", "Mac Pro", "Mac Studio"],
        series: ["M1", "M2", "Intel i5", "Intel i7", "27-inch 5K", "21.5-inch"]
    }
};


var IQA_GamingInventory = {
    "Sony": {
        models: ["Ps3", "Ps2", "Ps1", "PlayStation 5", "PlayStation 4", "PS VR2"],
        series: ["Disc Edition", "Digital Edition", "Pro", "Slim", "Fat", "Super Slim", "Classic"]
    },
    "Nintendo": {
        models: ["Switch", "Switch OLED", "Switch Lite"],
        series: ["Neon Blue/Red", "White", "Animal Crossing Edition"]
    },
    "Microsoft Gaming": {
        models: ["Xbox Series X", "Xbox Series S", "Xbox One X", "Xbox 360", "Xbox One"],
        series: ["1TB Black", "512GB White", "Halo Infinite Edition"]
    }
};

var gamingCategories = {
    "Consoles": ["PS5", "PS4", "Xbox Series X", "Xbox Series S", "Switch OLED", "Switch", "Retro Console"],
    "Controllers": ["DualSense", "DualShock 4", "Xbox Wireless Controller", "Elite Series 2", "Joy-Con", "Pro Controller"],
    "Games": ["Physical Disc", "Digital Code", "Cartridge"]
};

var cpuGenerations = [
    "i7-12th Gen", "i7-11th Gen", "i5-11th Gen", "i7-10th Gen", "i5-10th Gen",
    "i7-9th Gen", "i5-9th Gen", "i7-8th Gen", "i5-8th Gen", "6th - 7th Gen",
    "4th Gen & 5th", "2nd & 3rd Gen", "Ryzen 3", "Ryzen 5", "Ryzen 7", "AMD"
];

// Global support for all scripts
var IQA_Inventory = Object.assign({}, IQA_LaptopInventory, IQA_DesktopInventory, IQA_GamingInventory);

// Safely provide legacy name support without polluting with 'var' or 'const' that could cause redeclaration errors
if (!window.inventoryData) {
    window.inventoryData = IQA_Inventory;
}
