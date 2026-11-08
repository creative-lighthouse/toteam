// Tags, die auf einmal an mehrere Fotos kommen (Hochladen, Mehrfachauswahl) — gewählt mit
// PhotoTagFields, Form je Art wie in PhotoTagSection: { id, label, avatar?, hint? }.
// Räume tragen "r"/"a" vor der ID (Inventar-Raum bzw. Fläche auf einem Event-Lageplan).

export function emptyTagging() {
  return { people: [], characters: [], rooms: [], tags: [] }
}

export function hasTagging(tagging) {
  return Object.values(tagging).some(list => list.length > 0)
}

/** In die Felder der API (photos/upload, photos/bulkTag) */
export function taggingFields(tagging) {
  const roomIds = prefix => tagging.rooms.filter(r => String(r.id).startsWith(prefix)).map(r => Number(String(r.id).slice(1)))
  return {
    PeopleIDs: tagging.people.map(p => p.id),
    CharacterIDs: tagging.characters.map(c => c.id),
    RoomIDs: roomIds('r'),
    AreaIDs: roomIds('a'),
    Tags: tagging.tags.map(t => t.label),
  }
}
