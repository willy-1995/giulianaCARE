import React from "react";
import { useState } from "react";
import UserDataForm from "../assets/userdataform";
import { useNavigate } from "react-router-dom";
import type { ApiResponse } from "../types/api";
import { useLocation } from "react-router-dom";

export function EditUserAccount() {
  const [formData, setFormData] = useState({
    name: "",
    email: "",
    password: "",
    birthday: "",
    gender: "",
    height: "",
    ethnicity: "",
    location: "",
    home_location: "",
    degree: "",
    work: "",
    country: "",
    state: "",
    city: "",
    profiletext: "",
    category_1: "",
    category_2: "",
    premium: 0,
  });

  //message
  const [message, setMessage] = useState("");
  const location = useLocation();
  const navigate = useNavigate();

  // Prüfen, ob wir von der Registrierung kommen (Standard ist false)
  const isFromRegistration = location.state?.fromRegistration || false;

  //============
  // FUNCTIONS
  // ===========

  //=====REGISTRATION WITH EMAIL=====

  const registHandler = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    // e.currentTarget = form
    // await sendRequest(e.currentTarget);
    await updateProfile(e);
  };

  //UPDATE PROMPT
  const updateProfile = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const token = localStorage.getItem("token");
    try {
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
          // LOGIK-WEICHE:
          if (isFromRegistration) {
            // Wenn von Registrierung -> zum Foto-Upload
            navigate("/pictureUpload", { state: { fromRegistration: true } });
          } else {
            // Wenn normale Bearbeitung -> zum Profil
            navigate("/userprofile");
          }
        }, 3000);
      } else {
        setMessage(result.message);
      }
    } catch (error) {
      setMessage("Serverfehler");
    }
  };

  //===========
  //CLOSE
  const close = () => {
    if (confirm("Vorgang abbrechen?")) {
      navigate("/userprofile");
    }
  };
  return (
    <div className="body-div">
      <UserDataForm
        formData={formData}
        setFormData={setFormData}
        onSubmit={registHandler}
        message={message}
        buttonLabel="Speichern"
      />
      <button onClick={close}>Abbrechen</button>
    </div>
  );
}
