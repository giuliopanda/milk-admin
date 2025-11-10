class ValueCards {
    constructor(data, containerId, options = {}) {
        this.data = data;
        this.containerId = containerId;
        this.options = Object.assign({}, {
            maxHeight: '300px',           
            columnClass: 'col-auto mb-3',
            cardClass: 'card h-100',      
            cardBodyClass: 'card-body',
            titleClass: 'text-center mb-1 text-nowrap text-body-secondary',
            singleValueClass: 'display-6 text-nowrap',
            multiValueClass: 'h5 text-nowrap',
            maxCards: 200, // Updated limit to 200 cards
            showLimitAlert: true, // Show alert when cards are limited
            number_format: '' 
        }, options);
    }

    createCard(label, datasetValues) {
        const card = eI('div', this.options.cardClass);
        const cardBody = card.eI('div', this.options.cardBodyClass);

        // Create label as title
        cardBody.eI('div', {
            class: this.options.titleClass,
            text: label
        });

        // Create values container
        const valuesContainer = cardBody.eI('div', {
            class: 'values-container',
            style: datasetValues.length > 1 ? `
                max-height: ${this.options.maxHeight};
                overflow-y: auto;
                overflow-x: hidden;
            ` : `
                overflow-y: hidden;
                overflow-x: hidden;
            `
        });

        // Add values from all datasets
        datasetValues.forEach((item, index) => {
          //console.log ('ITEM INDEX ', item, index);
            const valueWrapper = valuesContainer.eI('div', 'text-center mt-2');
            
            // Add dataset label if we have multiple datasets
            if (datasetValues.length > 1) {
                valueWrapper.eI('div', {
                    class: 'small text-body-secondary',
                    text: item.datasetLabel,
                    'data-key': index // Using index as key, or item.key if available
                });
            }
            
            // Add the actual value with postfix if available
            let displayValue = item.value;
        
            // Store the raw value as a data attribute
            valueWrapper.eI('div', {
                class: datasetValues.length > 1 ? this.options.multiValueClass : this.options.singleValueClass,
                style: `color: ${item.backgroundColor}`,
                text: displayValue,
                'data-raw-value': displayValue,
                'data-number-format': this.options.number_format,
                'data-decimal': item.decimal,
                'data-postfix': item.postfix
            });
        });

        return card;
    }

    render() {
        // Get or create container
        let container = document.getElementById(this.containerId);
        if (container.tagName === 'CANVAS') {
            container = eI('div', { replaceChild: container });
            container.id = this.containerId;
        }

        // Clear container
        container.innerHTML = '';

        // Create a centering wrapper
        const centerWrapper = container.eI('div', 'd-flex justify-content-center w-100 js-layout-values ito-layout-values');
        
        // Create row wrapper with automatic width
        const row = centerWrapper.eI('div', 'row g-2');
        
        // Calculate how many cards to show (respecting the maxCards limit)
        const cardCount = Math.min(this.data.labels.length, this.options.maxCards);
        
        // Create a card for each label with all dataset values
        for (let i = 0; i < cardCount; i++) {
            const label = this.data.labels[i];
            
             let datasetValues = [];
          for (let j = 0; j < this.data.datasets.length; j++) {
              const dataset = this.data.datasets[j];
              
              // decimal
              let decimal = 0;
              if (Array.isArray(dataset.decimal)) {
                  decimal = dataset.decimal[i] ?? 0;
              } else if (dataset.decimal !== undefined) {
                  decimal = dataset.decimal;
              }

              // postfix
              let postfix = '';
              if (Array.isArray(dataset.postfix)) {
                  postfix = dataset.postfix[i] ?? '';
              } else if (dataset.postfix !== undefined) {
                  postfix = dataset.postfix;
              }

              // background
              let backgroundColor = '#111';
              if (Array.isArray(dataset.backgroundColor)) {
                  backgroundColor = dataset.backgroundColor[i] ?? '#111';
              } else if (dataset.backgroundColor !== undefined) {
                  backgroundColor = dataset.backgroundColor;
              }

              datasetValues.push({
                  value: dataset.data[i],
                  datasetLabel: dataset.label,
                  backgroundColor,
                  postfix,
                  decimal
              });
          }

          const column = row.eI('div', this.options.columnClass);
          column.appendChild(this.createCard(label, datasetValues));
        }
        
        // Add limit alert if we're showing fewer cards than total available
        if (this.options.showLimitAlert && this.data.labels.length > this.options.maxCards) {
            const alertDiv = container.eI('div', 'alert alert-info mt-3 text-center');
            alertDiv.innerHTML = `Showing only the first ${this.options.maxCards} items out of ${this.data.labels.length} total.`;
        }
        
        // Pass the number_format to ResponsiveChartLayout
        console.log('[Number format]:', this.options.number_format);
        window.responsiveChartLayout.numberFormat = this.options.number_format;
        window.responsiveChartLayout.setupContainer(container);
    }

    update(newData) {
        this.data = newData;
        this.render();
    }
}

/**
 * Enhanced Responsive Chart Layout
 * Automatically arranges and resizes chart boxes with advanced features
 */
class ResponsiveChartLayout {
    numberFormat = '';

    constructor(options = {}) {
      this.options = {
        containerSelector: options.containerSelector || '.js-layout-values',
        boxSelector: options.boxSelector || '.card',
        minBoxWidth: options.minBoxWidth || 180,
        maxBoxWidth: options.maxBoxWidth || 350,
        centerSingle: options.centerSingle !== false,
        fontSizeAdjust: options.fontSizeAdjust !== false,
        animateNumbers: options.animateNumbers !== false,
        className: options.className || 'js-responsive-layout',
        baseFontSize: options.baseFontSize || 2.4, // rem
        minFontSize: options.minFontSize || 1.4, // rem
        animationDuration: options.animationDuration || 1500 // ms
      };
      
      this.containers = [];
      this.animatedElements = new Set();
      
      // Add aggressive resize event listener
      this.addResizeListener();
      
      // Setup intersection observer for animations
      this.setupIntersectionObserver();
    }
    
    /**
     * Improve resize handling to ensure boxes scale properly
     */
    addResizeListener() {
      // Immediate resize handler (no debounce) for responsive behavior
      window.addEventListener('resize', () => {
        this.containers.forEach(container => {
          this.layoutBoxes(container);
        });
      });
      
      // Also check periodically for any size changes that might not trigger resize
      this.resizeInterval = setInterval(() => {
        this.containers.forEach(container => {
          if (container._lastWidth !== container.clientWidth) {
            container._lastWidth = container.clientWidth;
            this.layoutBoxes(container);
          }
        });
      }, 500);
    }
    
    setupIntersectionObserver() {
      // Create observer to detect when elements come into view
      this.observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const valueElement = entry.target;
            
            // Check if we haven't animated this element yet
            if (!this.animatedElements.has(valueElement)) {
              this.animateNumberValue(valueElement);
              this.animatedElements.add(valueElement);
            }
          }
        });
      }, {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
      });
    }
    
    setupContainer(container) {
      // Store initial width to detect changes
      container._lastWidth = container.clientWidth;
      
      // Add class to mark as initialized
      container.classList.add(this.options.className);
      
      // Track this container
      if (!this.containers.includes(container)) {
        this.containers.push(container);
      }
      
      // Create a flex container for the boxes if not already present
      let flexContainer = container.querySelector('.js-flex-container');
      if (!flexContainer) {
        // Get all existing boxes
        const existingBoxes = Array.from(container.querySelectorAll(this.options.boxSelector));
        
        // Create flex container
        flexContainer = document.createElement('div');
        flexContainer.className = 'js-flex-container ito-layout-values';
        
        // Move boxes into flex container
        if (existingBoxes.length) {
          existingBoxes.forEach(box => {
            // Find the closest parent that is a direct child of the container
            let boxParent = box;
            while (boxParent.parentElement !== container && boxParent.parentElement !== null) {
              boxParent = boxParent.parentElement;
              
              // Stop if we've reached a row/col structure
              if (boxParent.classList.contains('row') || boxParent.classList.contains('col') || 
                  boxParent.classList.contains('col-auto')) {
                break;
              }
            }
            
            // Add the box to the flex container, preserving original structure
            if (boxParent !== flexContainer) {
              flexContainer.appendChild(boxParent);
            }
          });
          
          // Append flex container to main container
          container.appendChild(flexContainer);
        }
      }
      
      // Apply styles to boxes
      const boxes = Array.from(container.querySelectorAll(this.options.boxSelector));
      boxes.forEach(box => {
        // Apply styling and enhancements
        this.enhanceBoxStyle(box);
        
        // Find value elements for animation
        const valueElements = box.querySelectorAll('.display-6');
        valueElements.forEach(el => {
          if (this.options.animateNumbers && this.isNumeric(el.textContent.trim())) {
            // Observe for animation
            this.observer.observe(el);
          }
        });
      });
      
      // Initial layout
      this.layoutBoxes(container);
    }
    
    enhanceBoxStyle(box) {
      // Basic box styling
      box.style.margin = '0';
      box.style.flexGrow = '1';  // Allow boxes to grow
      box.style.flexShrink = '1'; // Allow boxes to shrink
      box.style.position = 'relative';
      box.style.overflow = 'hidden';
      
      // Add stronger shadow and border for more emphasis
      box.style.boxShadow = '0 4px 12px rgba(0,0,0,0.08)';
      box.style.border = '1px solid rgba(0,0,0,0.1)';
      box.style.borderRadius = '8px';
      box.style.transition = 'all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1)';
      
      // Add extra CSS to handle animation state
      const styleElement = document.createElement('style');
      styleElement.textContent = `
        .card-animating {
          box-shadow: 0 6px 16px rgba(0,0,0,0.12) !important;
          border-color: rgba(74, 144, 226, 0.3) !important;
        }
      `;
      document.head.appendChild(styleElement);
      
      // Add top border gradient by default (not just on hover)
      const gradientElement = document.createElement('div');
      gradientElement.style.position = 'absolute';
      gradientElement.style.top = '0';
      gradientElement.style.left = '0';
      gradientElement.style.width = '100%';
      gradientElement.style.height = '3px';
      gradientElement.style.background = 'linear-gradient(90deg, #4a90e2, #50e3c2)';
      gradientElement.style.zIndex = '1';
      gradientElement.style.borderTopLeftRadius = '8px';
      gradientElement.style.borderTopRightRadius = '8px';
      
      // Insert the gradient element as the first child
      if (box.firstChild) {
        box.insertBefore(gradientElement, box.firstChild);
      } else {
        box.appendChild(gradientElement);
      }
      
      // Add hover effect
      box.addEventListener('mouseenter', () => {
        box.style.boxShadow = '0 8px 20px rgba(0,0,0,0.12)';
        box.style.transform = 'translateY(-2px)';
      });
      
      box.addEventListener('mouseleave', () => {
        // Only reset if not animating
        if (!box.classList.contains('card-animating')) {
          box.style.boxShadow = '0 4px 12px rgba(0,0,0,0.08)';
          box.style.transform = 'translateY(0)';
        }
      });
    }
    
    layoutBoxes(container) {
      const flexContainer = container.querySelector('.js-flex-container');
      if (!flexContainer) return;
      
      const boxes = Array.from(flexContainer.querySelectorAll(this.options.boxSelector));
      if (boxes.length === 0) return;
      
      // Get the ACTUAL width of the container (important for resizing)
      const containerWidth = flexContainer.offsetWidth;
      
      // Reduce gap for multiple rows to avoid too much spacing
      const isMultiRows = boxes.length > Math.floor(containerWidth / this.options.minBoxWidth);
      const gap = isMultiRows ? 8 : 16; // Smaller gap for multiple rows
      
      // Update gap in the flex container
      flexContainer.style.gap = `${gap}px`;
      
      // Calculate optimal box width based on container size
      let boxesPerRow = Math.max(1, Math.floor(containerWidth / this.options.minBoxWidth));
      boxesPerRow = Math.min(boxesPerRow, boxes.length);
      
      // Calculate if boxes will be in a single row or multiple rows
      const isMultipleRows = boxes.length > boxesPerRow;
      
      // Calculate box width
      let boxWidth;
      
      if (isMultipleRows) {
        // For multiple rows, make boxes fill the container width
        // Use a slightly tighter formula to reduce gaps
        boxWidth = Math.floor((containerWidth - (gap * (boxesPerRow - 1))) / boxesPerRow);
      } else {
        // For a single row with fewer boxes
        const idealWidth = Math.min(
          Math.floor((containerWidth - (gap * (boxes.length - 1))) / boxes.length),
          this.options.maxBoxWidth
        );
        
        // Make sure boxes grow when container grows (key fix)
        boxWidth = Math.max(idealWidth, this.options.minBoxWidth);
      }
      
      // Apply justification
      if (this.options.centerSingle && boxes.length <= boxesPerRow) {
        flexContainer.style.justifyContent = 'center';
      } else if (isMultipleRows) {
        // For multiple rows with tight spacing
        flexContainer.style.justifyContent = 'flex-start';
      } else {
        flexContainer.style.justifyContent = 'space-between';
      }
      
      // Apply new dimensions to boxes
      boxes.forEach(box => {
        // Set explicit width in pixels
        box.style.width = `${boxWidth}px`;
        box.style.flexBasis = `${boxWidth}px`;
        
        // For multiple rows, ensure consistent sizing
        if (isMultipleRows) {
          box.style.marginRight = '0';
          box.style.marginLeft = '0';
        }
        
        // Prevent box from exceeding max width
        box.style.maxWidth = `${this.options.maxBoxWidth}px`;
        
        // Apply proportional font sizing - but only check for overflow instead of auto-scaling
        this.adjustFontSizes(box, boxWidth);
      });
      
      // Force a layout recalculation
      flexContainer.offsetHeight;
      
      // Trigger a custom event in case other code needs to respond
      window.dispatchEvent(new CustomEvent('boxes-resized', { 
        detail: { container, boxes, boxWidth, isMultipleRows } 
      }));
    }
    
    adjustFontSizes(box, boxWidth) {
      // Enhanced font scaling based on box width
      const minWidth = this.options.minBoxWidth;
      const maxWidth = this.options.maxBoxWidth;
      const maxFontSize = this.options.baseFontSize; // in rem
      const minFontSize = this.options.minFontSize; // in rem
      
      // Calculate basic font size based on box width
      const normalizedWidth = (boxWidth - minWidth) / (maxWidth - minWidth);
      const baseScaleFactor = Math.min(Math.max(normalizedWidth, 0), 1);
      
      // Calculate font size with smoother scaling
      const fontSize = minFontSize + baseScaleFactor * (maxFontSize - minFontSize);
      
      // Apply to title elements
      const titles = box.querySelectorAll('.text-body-secondary');
      titles.forEach(title => {
        // Set a reasonable default size without auto-scaling
        const baseTitleSize = Math.max(fontSize * 0.55, 0.8);
        title.style.fontSize = `${baseTitleSize}rem`;
        title.style.lineHeight = '1.3';
        title.style.fontWeight = '500';
        
        // Only adjust if text doesn't fit
        if (this.isTextOverflowing(title)) {
          this.fitTextToContainer(title);
        }
      });
      
      // Apply to value elements
      const values = box.querySelectorAll('.display-6');
      values.forEach(value => {
        // Set initial font size based on box width
        let adjustedSize = fontSize;
        
        // Only adjust based on length for very long values
        const text = value.textContent.trim();
        if (text.length > 8) {
          const lengthFactor = Math.max(1 - ((text.length - 8) * 0.03), 0.8);
          adjustedSize *= lengthFactor;
        }
        
        value.style.fontSize = `${adjustedSize}rem`;
        value.style.lineHeight = '1.2';
        value.style.fontWeight = '500';
        value.style.color = '#333';
        
        // Only shrink text if it's actually overflowing
        if (this.isTextOverflowing(value)) {
          this.fitTextToContainer(value);
        }
        
        // Add a slight shadow to make values stand out more
        value.style.textShadow = '0 1px 2px rgba(0,0,0,0.05)';
      });
    }
    
    // Check if text is actually overflowing its container
    isTextOverflowing(element) {
      // Create a temporary span to measure text width
      const tempSpan = document.createElement('span');
      tempSpan.style.visibility = 'hidden';
      tempSpan.style.position = 'absolute';
      tempSpan.style.whiteSpace = 'nowrap';
      tempSpan.style.font = window.getComputedStyle(element).font;
      tempSpan.textContent = element.textContent;
      
      document.body.appendChild(tempSpan);
      const textWidth = tempSpan.offsetWidth;
      document.body.removeChild(tempSpan);
      
      // Get parent width (accounting for padding)
      const parentStyles = window.getComputedStyle(element.parentElement);
      const parentWidth = element.parentElement.offsetWidth - 
                          (parseFloat(parentStyles.paddingLeft) + 
                           parseFloat(parentStyles.paddingRight));
      
      // Return true if text is wider than container
      return textWidth > parentWidth;
    }
    
    // Fit text to container by reducing font size
    fitTextToContainer(element) {
      const parentStyles = window.getComputedStyle(element.parentElement);
      const parentWidth = element.parentElement.offsetWidth - 
                        (parseFloat(parentStyles.paddingLeft) + 
                         parseFloat(parentStyles.paddingRight));
      
      let fontSize = parseFloat(window.getComputedStyle(element).fontSize);
      let textWidth = element.scrollWidth;
      
      // Gradually reduce font size until text fits
      while (textWidth > parentWidth && fontSize > 8) {
        fontSize *= 0.9; // Reduce by 10% each step
        element.style.fontSize = `${fontSize}px`;
        
        // Create a temporary element to measure the new width accurately
        const tempSpan = document.createElement('span');
        tempSpan.style.visibility = 'hidden';
        tempSpan.style.position = 'absolute';
        tempSpan.style.whiteSpace = 'nowrap';
        tempSpan.style.font = window.getComputedStyle(element).font;
        tempSpan.textContent = element.textContent;
        
        document.body.appendChild(tempSpan);
        textWidth = tempSpan.offsetWidth;
        document.body.removeChild(tempSpan);
        
        if (fontSize < 8) break; // Avoid making text unreadably small
      }
    }
    
    ensureTextFits(element) {
      // Improved text fitting algorithm
      const tempSpan = document.createElement('span');
      tempSpan.style.visibility = 'hidden';
      tempSpan.style.position = 'absolute';
      tempSpan.style.whiteSpace = 'nowrap';
      tempSpan.style.font = window.getComputedStyle(element).font;
      tempSpan.textContent = element.textContent;
      
      document.body.appendChild(tempSpan);
      const textWidth = tempSpan.offsetWidth;
      document.body.removeChild(tempSpan);
      
      // Get parent width (accounting for padding)
      const parentStyles = window.getComputedStyle(element.parentElement);
      const parentWidth = element.parentElement.offsetWidth - 
                          (parseFloat(parentStyles.paddingLeft) + 
                           parseFloat(parentStyles.paddingRight));
      
      // If text is too wide, reduce font size
      if (textWidth > parentWidth) {
        const currentSize = parseFloat(window.getComputedStyle(element).fontSize);
        // Scale font to fit container
        const scaleFactor = parentWidth / textWidth * 0.95; // 95% to add a small margin
        const newSize = currentSize * scaleFactor;
        element.style.fontSize = `${newSize}px`;
      }
    }
    
    animateNumberValue(element) {
      const originalText = element.textContent.trim();
      
      // Store original for reference
      element.setAttribute('data-original', originalText);
      
      // Get parent card element to add animation effect to
      const parentCard = element.closest('.card');
      if (parentCard) {
        // Increase border radius during animation
        parentCard.style.transition = 'all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1)';
        parentCard.style.borderRadius = '12px';
        
        // Add animation class
        parentCard.classList.add('card-animating');
        
        // Reset after animation completes
        setTimeout(() => {
          parentCard.classList.remove('card-animating');
          parentCard.style.borderRadius = '8px';
        }, this.options.animationDuration + 500);
      }
      
      // Add animated class for pulse effect
      element.classList.add('animated');
      
      // Clean the text and convert to a number
      const endValue = this.parseNumberFromString(originalText);
      if (endValue === null) return; // Not a valid number
      
      // Handle decimals
      const isDecimal = originalText.includes('.');
      const decimalPlaces = isDecimal ? 
        originalText.split('.')[1].length : 0;
      
      // Format preserving original format (commas, decimals)
      const hasComma = originalText.includes(',');
      
      // Start from zero
      let startValue = 0;
      let currentValue = startValue;
      
      // Determine animation properties for a more pleasing effect
      const totalSteps = 75; // More steps for smoother animation
      const duration = this.options.animationDuration;
      
      const decimal = element.getAttribute('data-decimal');
      const postfix = element.getAttribute('data-postfix');
      
      // Set initial value
      element.textContent = formatNumber(startValue, this.numberFormat, decimal, postfix);
      
      // Enhanced easing function with a bounce at the end
      const easeFunction = t => {
        // Base easing (cubic)
        const cubic = 1 - Math.pow(1 - t, 3);
        
        // Add gentle bounce at the end
        if (t > 0.8) {
          const bouncePhase = (t - 0.8) / 0.2; // Normalize to 0-1 for the bounce phase
          const bounce = 1 + Math.sin(bouncePhase * Math.PI * 2) * 0.03 * Math.pow(1 - bouncePhase, 2);
          return cubic * bounce;
        }
        
        return cubic;
      };
      
      let step = 0;
      const animate = () => {
        step++;
        
        // Apply easing for smoother animation
        const progress = step / totalSteps;
        const easedProgress = easeFunction(progress);
        
        // Calculate current value based on eased progress
        currentValue = startValue + (endValue - startValue) * easedProgress;
        const decimal = element.getAttribute('data-decimal');
        const postfix = element.getAttribute('data-postfix');
        element.textContent = formatNumber(currentValue, this.numberFormat, decimal, postfix);
        
        // Check if text overflows and adjust if needed during animation
        if (this.isTextOverflowing(element)) {
          this.fitTextToContainer(element);
        }
        
        if (step < totalSteps) {
          requestAnimationFrame(animate);
        } else {
          // Format the final value instead of using the original text
          // This ensures we maintain the number formatting
          const decimal = element.getAttribute('data-decimal');
          const postfix = element.getAttribute('data-postfix');
          element.textContent = formatNumber(endValue, this.numberFormat, decimal, postfix);
          
          // Check one last time for overflow
          if (this.isTextOverflowing(element)) {
            this.fitTextToContainer(element);
          }
          
          // Remove animated class after a delay
          setTimeout(() => {
            element.classList.remove('animated');
          }, 1000);
        }
      };
      
      requestAnimationFrame(animate);
    }
    
    parseNumberFromString(str) {
      // Remove all non-numeric characters except decimal point
      const cleanedStr = str.replace(/[^\d.-]/g, '');
      const number = parseFloat(cleanedStr);
      return isNaN(number) ? null : number;
    }
    
    isNumeric(str) {
      // Check if string contains digits and can potentially be animated
      return /\d/.test(str) && !isNaN(this.parseNumberFromString(str));
    }
    
    // Utility function to debounce resize events
    debounce(func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    }
    
    // Cleanup resources
    destroy() {
      // Clear interval
      if (this.resizeInterval) {
        clearInterval(this.resizeInterval);
      }
      
      // Disconnect observer
      if (this.observer) {
        this.observer.disconnect();
      }
      
      // Clear tracked elements
      this.containers = [];
      this.animatedElements.clear();
    }
  }
  
  // Export the class for module environments
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = ResponsiveChartLayout;
  }
  // Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    window.responsiveChartLayout = new ResponsiveChartLayout({
      containerSelector: '.js-layout-values',
      minBoxWidth: 200,
      maxBoxWidth: 350,
      fontSizeAdjust: true
    });
});