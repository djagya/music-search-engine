export interface TypingResponse {
  maxScore: number;
  total: { value: number; relation: string };
  hits: Suggestion[];
}

// todo: what other information is needed? relations, number of results, score?...
// todo: it must have "ids" field for items grouped by value, not "id".
export interface Suggestion {
  _id: string;
  _index: string;
  _score: number;
  id: string;
  value: string;
  // The list of items ids which have the exact same "value".
  ids: string;
}

export interface RelatedSuggestion {
  id: string;
  value: string;
  // todo: relation or what? or maybe index/field name?
}

export interface ErrorResponse {
    error: string;
}
