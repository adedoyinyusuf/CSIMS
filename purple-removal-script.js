/**
 * CSIMS Purple Removal Script
 * Ensures no purple styling appears anywhere in the application
 * Run this script on page load to catch any remaining purple elements
 */

(function() {
    'use strict';
    
    // Professional color replacements
    const colorReplacements = {
        // Purple color mappings to professional blue-gray
        '#663399': '#1A5599', // Purple -> Lapis Lazuli
        '#8B008B': '#336699', // Dark Magenta -> True Blue  
        '#9932CC': '#334155', // Dark Orchid -> Paynes Gray
        '#8A2BE2': '#475569', // Blue Violet -> Charcoal
        '#9400D3': '#1A5599', // Dark Violet -> Lapis Lazuli
        '#9370DB': '#336699', // Medium Purple -> True Blue
        '#7B68EE': '#334155', // Medium Slate Blue -> Paynes Gray
        'purple': '#1A5599', // Generic purple -> Lapis Lazuli
        'violet': '#475569'   // Generic violet -> Charcoal
    };
    
    // CSS class replacements
    const classReplacements = {
        'bg-purple': 'bg-blue',
        'text-purple': 'text-blue', 
        'border-purple': 'border-blue',
        'from-purple': 'from-blue',
        'to-purple': 'to-blue'
    };
    
    function removePurpleElements() {
        // Remove purple background colors
        const elementsWithPurple = document.querySelectorAll('*');
        
        elementsWithPurple.forEach(element => {
            const computedStyle = window.getComputedStyle(element);
            const bgColor = computedStyle.backgroundColor;
            const color = computedStyle.color;
            
            // Check if background color contains purple tones
            if (bgColor.includes('rgb(') && isPurpleColor(bgColor)) {
                element.style.background = 'linear-gradient(135deg, #1A5599 0%, #336699 100%)';
                element.style.color = '#FFFFFF';
                console.log('Replaced purple background on element:', element);
            }
            
            // Check if text color is purple
            if (color.includes('rgb(') && isPurpleColor(color)) {
                element.style.color = '#1A5599';
                console.log('Replaced purple text on element:', element);
            }
            
            // Check for inline styles with purple
            const inlineStyle = element.getAttribute('style');
            if (inlineStyle && containsPurple(inlineStyle)) {
                const newStyle = replacePurpleInStyle(inlineStyle);
                element.setAttribute('style', newStyle);
                console.log('Replaced purple in inline style:', element);
            }
        });
        
        // Remove purple CSS classes
        document.querySelectorAll('[class*="purple"], [class*="violet"]').forEach(element => {
            const classes = element.className.split(' ');
            let updated = false;
            
            const newClasses = classes.map(cls => {
                for (const [oldClass, newClass] of Object.entries(classReplacements)) {
                    if (cls.includes(oldClass)) {
                        updated = true;
                        return cls.replace(oldClass, newClass);
                    }
                }
                return cls;
            });
            
            if (updated) {
                element.className = newClasses.join(' ');
                console.log('Updated purple classes on element:', element);
            }
        });
    }
    
    function isPurpleColor(colorString) {
        // Convert RGB string to values
        const match = colorString.match(/rgb\\((\\d+),\\s*(\\d+),\\s*(\\d+)\\)/);
        if (!match) return false;
        
        const [, r, g, b] = match.map(Number);
        
        // Detect purple hues (high red and blue, low green)
        return (r > 100 && b > 100 && g < 150) || 
               (r > 130 && b > 130 && r > g && b > g);
    }
    
    function containsPurple(styleString) {
        const purpleKeywords = ['purple', 'violet', '#663399', '#8B008B', '#9932CC', '#8A2BE2'];
        return purpleKeywords.some(keyword => styleString.toLowerCase().includes(keyword.toLowerCase()));
    }
    
    function replacePurpleInStyle(styleString) {
        let newStyle = styleString;
        
        for (const [oldColor, newColor] of Object.entries(colorReplacements)) {
            const regex = new RegExp(oldColor, 'gi');
            newStyle = newStyle.replace(regex, newColor);
        }
        
        return newStyle;
    }
    
    function addPurpleOverrideStyles() {
        // Create and inject additional CSS to override any remaining purple
        const style = document.createElement('style');
        style.textContent = `
            /* Emergency purple overrides - highest specificity */
            [style*="purple"] !important,
            [style*="violet"] !important,
            [style*="#663399"] !important,
            [style*="#8B008B"] !important,
            [style*="#9932CC"] !important {
                background: linear-gradient(135deg, #1A5599 0%, #336699 100%) !important;
                color: #FFFFFF !important;
            }
            
            /* Override any remaining Tailwind purple classes */
            [class*="bg-purple"] {
                background: linear-gradient(135deg, #1A5599 0%, #336699 100%) !important;
                color: #FFFFFF !important;
            }
            
            [class*="text-purple"] {
                color: #1A5599 !important;
            }
            
            [class*="border-purple"] {
                border-color: #1A5599 !important;
            }
            
            /* Gradient overrides */
            .from-purple-600, .from-purple-700, .from-purple-800 {
                --tw-gradient-from: #1A5599 !important;
            }
            
            .to-purple-600, .to-purple-700, .to-purple-800 {
                --tw-gradient-to: #336699 !important;
            }
        `;
        
        document.head.appendChild(style);
        console.log('Added emergency purple override styles');
    }
    
    // Run the purple removal functions
    function initPurpleRemoval() {
        removePurpleElements();
        addPurpleOverrideStyles();
        
        // Run again after a short delay to catch dynamically loaded content
        setTimeout(removePurpleElements, 500);
        
        // Set up a mutation observer to catch future changes
        const observer = new MutationObserver(function(mutations) {
            let shouldCheck = false;
            
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' || 
                    (mutation.type === 'attributes' && 
                     (mutation.attributeName === 'class' || mutation.attributeName === 'style'))) {
                    shouldCheck = true;
                }
            });
            
            if (shouldCheck) {
                setTimeout(removePurpleElements, 100);
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'style']
        });
        
        console.log('CSIMS Purple Removal Script initialized successfully');
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPurpleRemoval);
    } else {
        initPurpleRemoval();
    }
    
})();