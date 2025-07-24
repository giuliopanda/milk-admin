<?php
namespace Modules\docs;
/**
 * @title  Javascript eI utility
 * @category Theme
 * @order 50
 * @tags eI-utility, DOM-manipulation, JavaScript-helpers, element-creation, event-handling, DOM-utilities, vanilla-JavaScript, javascript, dom, elements, eI, eIs, dom-manipulation, events, toggle, hide-show, elHide, elShow, elRemove, toggleEl, sortable, drag-drop, ItoSortableList, fetch, ajax, permissions, error-handling, forms, checkbox, select, dataset, animation, styles, classes, appendChild, createElement, querySelector, getElementById, event-listener, callback, vanilla-js, framework, utility, helper-functions, json, response, toast, notifications, handlers, hooks, components, attributes, css, html, dynamic, interactive
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">

<h1 class="mb-4"><code>eI()</code> <code>eIs()</code> Functions</h1>

<section class="mb-5">
    <h2>Creating a new element</h2>
    <div class="example-box">
        <div class="row">
            <div class="col-md-6">
                <h4>Vanilla JavaScript</h4>
                <div class="code-box">
                    <pre><code>const newElement = document.createElement('div', 'my class');</code></pre>
                </div>
            </div>
            <div class="col-md-6">
                <h4>With <code>eI()</code></h4>
                You can use the same createElement syntax
                <div class="code-box">
                    <pre><code>const newElement = eI('div', 'my class');</code></pre>
                </div>  
                Or you can directly write HTML
                <div class="code-box"> 
                    <pre><code>const newElement = eI('&lt;div class="my class"&gt;Text&lt;/div&gt;');</code></pre>
                </div>  
            </div>
        </div>
    </div>
</section>

<section class="mb-5">
    <h2>Selecting an element by ID</h2>
    <div class="example-box">
        <div class="row">
            <div class="col-md-6">
                <h4>Vanilla JavaScript</h4>
                <div class="code-box">
                    <pre><code>const newElement = document.getElementById('myId');</code></pre>
                </div>
            </div>
            <div class="col-md-6">
                <h4>With <code>eI()</code></h4>
                <div class="code-box">
                    <pre><code>const newElement = eI('#myId');</code></pre>
                </div>   
            </div>
        </div>
    </div>
</section>

<section class="mb-5">
    <h2>Selecting an element by classes</h2>
    <div class="example-box">
        <div class="row">
            <div class="col-md-6">
                <h4>Vanilla JavaScript</h4>
                <div class="code-box">
                    <pre><code>const newElement = document.querySelector('.jsClass');</code></pre>
                </div>
            </div>
            <div class="col-md-6">
                <h4>With <code>eI()</code></h4>
                <div class="code-box">
                    <pre><code>const newElement = eI('.jsClass');</code></pre>
                </div>   
            </div>
        </div>
    </div>
</section>

<section class="mb-5">
    <h2>AppendTo</h2>
    <div class="example-box">
        <div class="row">
            <div class="col-md-6">
                <h4>Vanilla JavaScript</h4>
                <div class="code-box">
                    <pre><code>el = createElement('div');
el.textContent = 'My Div';
container.appendChild(el)</code></pre>
                </div>
            </div>
            <div class="col-md-6">
                <h4>With <code>eI()</code></h4>
                <div class="code-box">
                    <pre><code>eI('div', {text: 'My Div', to: container});</code></pre>
                </div>   
                Or eI(el).eI(el2) returns the DOM of el2!
                <div class="code-box">
                    <pre><code>eI(container).eI('div', {text: 'My Div'});</code></pre>
                </div>    
            </div>
        </div>
    </div>
</section>

<section class="mb-5">
    <h2>Modifying an existing element</h2>
    <div class="row">
        <div class="col-md-6">
            <div class="example-box">
                <h4>Vanilla JavaScript</h4>
                <div class="code-box">
                    <pre><code>const targetElement = document.getElementById('targetElement');
targetElement.textContent = 'Modified text!';
targetElement.style.color = 'red';</code></pre>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="example-box">
                <h4>With <code>eI()</code></h4>
                <div class="code-box">
                    <pre><code>eI('#targetElement', {
text: 'Modified text!',
style: { color: 'red' }
});</code></pre>
                </div>
            </div>
        </div>
    </div>
</section>


<section class="mb-5">
    <h2>Adding an event</h2>
    <div class="row">
        <div class="col-md-6">
            <div class="example-box">
                <h4>Vanilla JavaScript</h4>
                <div class="code-box">
                    <pre><code>const button = document.getElementById('exampleButton');
button.addEventListener('click', () => {
alert('You clicked the button!');
});</code></pre>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="example-box">
                <h4>With <code>eI()</code></h4>
                <div class="code-box">
                    <pre><code>eI('#exampleButton', {
click: () => alert('You clicked the button!')
});</code></pre>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="mb-5">
    <h2>Replacing an existing element</h2>
    <div class="row">
        <div class="col-md-6">
            <div class="example-box">
                <h4>Vanilla JavaScript</h4>
                <div class="code-box">
                    <pre><code>const oldElement = document.getElementById('oldElement');
const newElement = document.createElement('div');
newElement.textContent = 'New element!';
oldElement.parentNode.replaceChild(newElement, oldElement);</code></pre>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="example-box">
                <h4>With <code>eI()</code></h4>
                <div class="code-box">
                    <pre><code>eI('&lt;div&gt;New element!&lt;/div&gt;', {
replaceChild: '#oldElement'
});</code></pre>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="mb-5">
    <h2>Adding styles and classes</h2>
    <div class="row">
        <div class="col-md-6">
            <div class="example-box">
                <h4>Vanilla JavaScript</h4>
                <div class="code-box">
                    <pre><code>const styledElement = document.getElementById('styledElement');
styledElement.classList.add('text-success', 'fw-bold');
styledElement.style.fontSize = '20px';</code></pre>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="example-box">
                <h4>With <code>eI()</code></h4>
                <div class="code-box">
                    <pre><code>eI('#styledElement', {
class: 'text-success fw-bold',
style: { fontSize: '20px' }
});</code></pre>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="mb-5">
    <h2>Adding an element with multiple attributes</h2>
    <div class="row">
        <div class="col-md-6">
            <div class="example-box">
                <h4>Vanilla JavaScript</h4>
                <div class="code-box">
                    <pre><code>const customElement = document.createElement('div');
customElement.textContent = 'Element with custom attributes';
customElement.setAttribute('data-custom', 'value');
document.getElementById('example6').appendChild(customElement);</code></pre>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="example-box">
                <h4>With <code>eI()</code></h4>
                <div class="code-box">
                    <pre><code>eI('&lt;div data-custom="value"&gt;Element with custom attributes&lt;/div&gt;', {
to: '#example6'
});</code></pre>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="mb-5">
    <h2>Removing an element</h2>
    <div class="row">
        <div class="col-md-6">
            <div class="example-box">
                <h4>Vanilla JavaScript</h4>
                <div class="code-box">
                    <pre><code>const elementToRemove = document.getElementById('elementToRemove');
elementToRemove.parentNode.removeChild(elementToRemove);</code></pre>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="example-box">
                <h4>With <code>eI()</code></h4>
                <div class="code-box">
                    <pre><code>eI('#elementToRemove', {
remove: true
});</code></pre>
                </div>
            </div>
        </div>
    </div>
</section>


    <!-- Available options table -->
    <section class="mb-5">
    <h2>Available options for <code>eI()</code></h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Option</th>
                <th>Type</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>to</code></td>
                <td><code>string</code> or <code>HTMLElement</code></td>
                <td>Adds the element as a child of another element.</td>
            </tr>
            <tr>
                <td><code>before</code></td>
                <td><code>string</code> or <code>HTMLElement</code></td>
                <td>Inserts the element before another element.</td>
            </tr>
            <tr>
                <td><code>after</code></td>
                <td><code>string</code> or <code>HTMLElement</code></td>
                <td>Inserts the element after another element.</td>
            </tr>
            <tr>
                <td><code>replace</code></td>
                <td><code>string</code> or <code>HTMLElement</code></td>
                <td>Replaces the content of an element with the current element.</td>
            </tr>
            <tr>
                <td><code>replaceChild</code></td>
                <td><code>string</code> or <code>HTMLElement</code></td>
                <td>Completely replaces an element with the current element.</td>
            </tr>
            <tr>
                <td><code>remove</code></td>
                <td><code>boolean</code></td>
                <td>Removes the element from the DOM.</td>
            </tr>
            <tr>
                <td><code>click</code>, <code>mouseover</code>, etc.</td>
                <td><code>function</code></td>
                <td>Adds event handlers like <code>click</code>, <code>mouseover</code>, etc.</td>
            </tr>
            <tr>
                <td><code>style</code></td>
                <td><code>object</code></td>
                <td>Applies CSS styles to the element.</td>
            </tr>
            <tr>
                <td><code>class</code></td>
                <td><code>string</code> or <code>array</code></td>
                <td>Adds one or more classes to the element.</td>
            </tr>
            <tr>
                <td><code>removeClass</code></td>
                <td><code>string</code></td>
                <td>Removes a specific class from the element.</td>
            </tr>
            <tr>
                <td><code>replaceClass</code></td>
                <td><code>array</code></td>
                <td>Replaces one class with another.</td>
            </tr>
            <tr>
                <td><code>id</code></td>
                <td><code>string</code></td>
                <td>Sets the element's ID.</td>
            </tr>
            <tr>
                <td><code>text</code></td>
                <td><code>string</code></td>
                <td>Sets the element's text.</td>
            </tr>
            <tr>
                <td><code>html</code></td>
                <td><code>string</code></td>
                <td>Sets the element's inner HTML.</td>
            </tr>
        </tbody>
    </table>
</section>


<section class="mb-5">
    <h2>Using eI as a DOM function</h2>
    <p>DOM elements called by eI acquire two methods: eI and eIs. <br><code>eI(el1).eI(el2)</code>. The el2 is inserted inside el1 via appendChild. The function returns el2.</p>
    <div class="example-box">
        <div class="row">
            <div class="col-md-6">
                <h4>Vanilla JavaScript</h4>
                <div class="code-box">
                    <pre><code>// Create a div with classes and add it to a container
const newElement = document.createElement('div');
newElement.classList.add('alert', 'alert-info');
newElement.textContent = 'Element created with createElement';
document.getElementById('createElement').appendChild(newElement);</code></pre>
                </div>
            </div>
            <div class="col-md-6">
                <h4>With <code>eI()</code></h4>
                <div class="code-box">
                    <pre><code>// Create a div with classes and add it to a container
el = eI('#createElement').eI('div', 'alert alert-info');
el.textContent = 'Element created with createElement';</code></pre>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Example 3: Adding properties to elements at any time -->
<section class="mb-5">
    <h2>Adding properties to elements at any time</h2>
    <div class="example-box">
        <div class="row">
            <div class="col-md-6">
                <h4>Vanilla JavaScript</h4>
                <div class="code-box">
                    <pre><code>
const dynamicElement = document.getElementById('dynamicElement');
dynamicElement.textContent = 'Element created with createElement';</code></pre>
                </div>
            </div>
            <div class="col-md-6">
                <h4>With <code>eI()</code></h4>
                <div class="code-box">
                    <pre><code>eI('#dynamicElement', { text: 'Element created with createElement' });</code></pre>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Example 4: Using eIs() with a selector and a function -->
<section class="mb-5">
    <h2>4. Using <code>eIs()</code> with a selector and a function</h2>
    <div class="example-box">
        <div class="row">
            <div class="col-md-6">
                <h4>Vanilla JavaScript</h4>
                <div class="code-box">
                    <pre><code>// Apply a function to all &lt;li&gt; elements within a &lt;ul&gt;
const listItems = document.querySelectorAll('ul li');
listItems.forEach((el, i) => {
el.classList.add('list-item');
el.textContent = `Element ${i + 1}`;
});</code></pre>
                </div>
            </div>
            <div class="col-md-6">
                <h4>With <code>eIs()</code></h4>
                <div class="code-box">
                    <pre><code>// Apply a function to all &lt;li&gt; elements within a &lt;ul&gt;
eIs('ul li', (el, i) => {
el.classList.add('list-item');
el.textContent = `Element ${i + 1}`;
});</code></pre>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Example 5: Using eIs() with a NodeList and a function -->
<section class="mb-5">
    <h2>5. Using <code>eIs()</code> with a NodeList and a function</h2>
    <div class="example-box">
        <div class="row">
            <div class="col-md-6">
                <h4>Vanilla JavaScript</h4>
                <div class="code-box">
                    <pre><code>// Get a NodeList of all elements with the .myClass class
const nodeList = document.querySelectorAll('.myClass');

// Apply a function to all elements in the NodeList
nodeList.forEach((el, i) => {
el.classList.add('highlight');
el.textContent = `Element ${i + 1} highlighted`;
});</code></pre>
                </div>
            </div>
            <div class="col-md-6">
                <h4>With <code>eIs()</code></h4>
                <div class="code-box">
                    <pre><code>// Get a NodeList of all elements with the .myClass class
const nodeList = document.querySelectorAll('.myClass');

// Apply a function to all elements in the NodeList
eIs(nodeList, (el, i) => {
el.classList.add('highlight');
el.textContent = `Element ${i + 1} highlighted`;
});</code></pre>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Example 8: Adding an element with animation -->
<section class="mb-5">
    <h2>8. Adding an element with animation</h2>
    <div class="row">
        <div class="col-md-6">
            <div class="example-box">
                <h4>Vanilla JavaScript</h4>
                <div class="code-box">
                    <pre><code>document.getElementById('addButton').addEventListener('click', () => {
const newElement = document.createElement('div');
newElement.textContent = 'Element ' + counter;
newElement.classList.add('box', 'fade-in');
document.getElementById('container').appendChild(newElement);
counter++;
});</code></pre>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="example-box">
                <h4>With <code>eI()</code></h4>
                <div class="code-box">
                    <pre><code>let counter = 1;
eI('#addButton', {
click: () => {
eI('&lt;div class="box fade-in"&gt;Element ' + counter + '&lt;/div&gt;', {
    to: '#container'
});
counter++;
}
});</code></pre>
                </div>
            </div>
        </div>
    </div>
</section>

</div>

</div>