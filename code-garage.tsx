const updateProfile = async (e: React.FormEvent<HTMLFormElement>) => {
  e.preventDefault();
  const token = localStorage.getItem("token");

  const response = await fetch(
    "http://localhost/datingapp_ki/api/users_manager.php",
    {
      method: "PUT",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify(formData),
    },
  );

  const result = await response.json();

  if (result.success) {
    setMessage(result.message);
    setTimeout(() => {
      setMessage("");
      onSuccess();
    }, 3000);
  } else {
    setMessage(result.message);
  }
};
