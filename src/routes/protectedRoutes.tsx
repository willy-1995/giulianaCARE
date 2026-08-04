import React from "react";
import { Navigate } from "react-router-dom";

interface Props {
  children: React.ReactNode;
}

export const ProtectedRoute = ({ children }: Props) => {
  const token = localStorage.getItem("token");

  // Wenn kein Token vorhanden ist, wird der User sofort zum Login geschickt.
  // "replace" sorgt dafür, dass er nicht mit dem "Zurück"-Button im Browser
  // wieder auf die geschützte Seite kommt.
  if (!token) {
    return <Navigate to="/login" replace />;
  }

  // Wenn ein Token da ist, darf er die Seite sehen.
  return <>{children}</>;
};
