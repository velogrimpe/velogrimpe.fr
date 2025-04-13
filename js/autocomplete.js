function setupAutocomplete(
  inputId,
  listId,
  datalistId,
  selectCallback = () => {}
) {
  const input = document.getElementById(inputId);
  const list = document.getElementById(listId);
  const datalist = document.getElementById(datalistId);
  const options = Array.from(datalist.options).map((option) => option.value);
  let currentFocus = -1;

  input.addEventListener("input", () => {
    const value = input.value.toLowerCase();
    list.innerHTML = "";
    currentFocus = -1;

    if (value) {
      const filteredOptions = options.filter((option) =>
        matchOption(option, value)
      );

      filteredOptions.forEach((option, index) => {
        const li = document.createElement("li");
        li.textContent = option;
        li.classList.add(
          "p-2",
          "cursor-pointer",
          "hover:bg-primary",
          "hover:text-white"
        );
        li.addEventListener("click", (e) => {
          selectCallback(option);
          input.value = option;
          list.classList.add("hidden");
        });
        list.appendChild(li);
      });

      list.classList.remove("hidden");
    } else {
      list.classList.add("hidden");
    }
  });

  input.addEventListener("keydown", (e) => {
    let items = list.getElementsByTagName("li");
    if (e.key === "ArrowDown") {
      currentFocus++;
      addActive(items);
      ensureVisible(items);
    } else if (e.key === "ArrowUp") {
      currentFocus--;
      addActive(items);
      ensureVisible(items);
    } else if (e.key === "Enter") {
      e.preventDefault();
      if (currentFocus > -1) {
        items[currentFocus].click();
      }
    } else if (e.key === "Escape") {
      e.preventDefault();
      input.value = "";
      list.classList.add("hidden");
      currentFocus = -1;
    }
  });

  input.addEventListener("blur", () => {
    if (!options.includes(input.value)) {
      input.value = "";
      setTimeout(() => list.classList.add("hidden"), 200);
    }
  });

  function addActive(items) {
    if (!items) return false;
    removeActive(items);
    if (currentFocus >= items.length) currentFocus = 0;
    if (currentFocus < 0) currentFocus = items.length - 1;
    items[currentFocus].classList.add("autocomplete-active");
  }

  function removeActive(items) {
    for (let i = 0; i < items.length; i++) {
      items[i].classList.remove("autocomplete-active");
    }
  }

  function ensureVisible(items) {
    if (currentFocus < 0 || currentFocus >= items.length) return;
    const activeItem = items[currentFocus];
    activeItem.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }

  function matchOption(option, value) {
    const normalizedOption = option
      .toLowerCase()
      .normalize("NFD")
      .replace(/[-']/, " ")
      .replace(/[\u0300-\u036f]/g, "");
    const normalizedValue = value
      .normalize("NFD")
      .replace(/[-']/, " ")
      .replace(/[\u0300-\u036f]/g, "");

    // Inclusion
    if (normalizedOption.includes(normalizedValue)) return true;

    // Tolérance à une faute de frappe, même préfixe
    let errors = 0;
    const maxErrors = value.length > 4 ? 1 : 0;
    for (let i = 0; i < normalizedValue.length; i++) {
      if (normalizedOption[i] !== normalizedValue[i]) {
        errors++;
        if (errors > maxErrors) return false;
      }
    }

    return true;
  }
}
