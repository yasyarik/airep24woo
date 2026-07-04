(function () {
  var activeColorInput = null;
  var colorInputs = document.querySelectorAll('input[name="primary_color"], input[name="background_color"]');

  colorInputs.forEach(function (input) {
    input.addEventListener('focus', function () {
      activeColorInput = input;
    });
    input.addEventListener('click', function () {
      activeColorInput = input;
    });
  });

  document.querySelectorAll('[data-airep24-color]').forEach(function (button) {
    button.addEventListener('click', function () {
      var value = button.getAttribute('data-airep24-color');
      if (!value || !activeColorInput) {
        return;
      }
      if (/^#[0-9a-fA-F]{6}$/.test(value) || /^linear-gradient\(/i.test(value)) {
        activeColorInput.value = value;
        activeColorInput.dispatchEvent(new Event('input', { bubbles: true }));
        activeColorInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  });
})();
