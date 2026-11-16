import React, { useEffect, useState } from "react";
import { useParams } from "react-router-dom";

export default function CarematchProfileView() {
  const { id } = useParams<{ id: string }>();
  const [profile, setProfile] = useState<any>(null);

  useEffect(() => {
    fetch(`/carematch_api.php?action=profile&id=${id}`, {
      headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) setProfile(data.user);
      });
  }, [id]);

  if (!profile) return <div>Lade Profil...</div>;

  return (
    <div>
      <h2>Profil von {profile.name}</h2>
      <p>Region: {profile.region}</p>
      <p>Sprachen: {profile.languages}</p>
    </div>
  );
}
