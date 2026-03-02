<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Dentech Login</title>

  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    .fade {
      animation: up 0.5s ease-out;
    }
    @keyframes up {
      from { opacity: 0; transform: translateY(12px); }
      to   { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>

<body class="bg-gray-100 min-h-screen flex justify-center items-center">

  <!-- Container -->
  <div class="bg-white border border-gray-200 shadow-sm w-full max-w-sm p-10 rounded-xl fade">

    <!-- Logo -->
    <h1 class="text-3xl font-semibold text-center mb-8 text-gray-800">
      Dentech
    </h1>

    <!-- Form -->
    <form action="#" method="POST" class="space-y-6">

      <!-- Usuário -->
      <div>
        <label class="text-gray-700 text-sm">Usuário</label>
        <input type="text" 
               class="w-full mt-1 p-3 rounded-lg bg-gray-100 border border-gray-300 outline-none focus:ring-2 focus:ring-gray-600 transition"
               placeholder="Digite seu usuário">
      </div>

      <!-- Senha -->
      <div>
        <label class="text-gray-700 text-sm">Senha</label>
        <input type="password" 
               class="w-full mt-1 p-3 rounded-lg bg-gray-100 border border-gray-300 outline-none focus:ring-2 focus:ring-gray-600 transition"
               placeholder="Digite sua senha">
      </div>

      <!-- Botão -->
      <button 
        class="w-full p-3 bg-gray-900 hover:bg-gray-700 text-white rounded-lg font-medium transition">
        Entrar
      </button>

      <!-- Link -->
      <p class="text-center text-gray-500 text-sm hover:text-gray-700 cursor-pointer transition">
        Esqueceu a senha?
      </p>

    </form>
  </div>

</body>
</html>
