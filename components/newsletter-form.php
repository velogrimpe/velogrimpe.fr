<div class="w-full flex flex-col items-center">
  <form action="/api/newsletter_subscribe.php" method="post"
    class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4 max-w-full p-4 rounded-lg">
    <label class="input input-primary flex items-center gap-2 w-full">
      <input class="grow" type="email" id="email" name="email" placeholder="Votre email" required />
      <svg class="w-4 h-4 fill-none stroke-current">
        <use href="#mail-line"></use>
      </svg>
    </label>
    <button class="btn btn-primary" type="submit">S'inscrire à la newsletter</button>
  </form>
</div>