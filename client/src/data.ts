import axios from 'axios';
import { RelatedSuggestion, Suggestion } from './types';

const example: Suggestion[] = [
    { id: '1', value: 'Item 1' },
    { id: '2', value: 'Item 2' },
    { id: '3', value: 'Item 3' },
];

const relatedExample: RelatedSuggestion[] = [
    { id: 'rel1', value: 'Related 1' },
    { id: 'rel2', value: 'Related 2' },
    { id: 'rel3', value: 'Related 3' },
];

export function fetchSuggestions(field: string, value: string): Promise<Suggestion[]> {
    // todo: maybe do a direct request to elasticsearch? then there's no need in backend server
    const filtered = value.trim();
    return axios
        .get('/search', { params: { f: field, q: filtered } })
        .then(res => {
            console.log(res);
            return res.data || [];
        })
        .catch(err => {
            console.log(err);
            // return [];
            return example;
        });
}

interface RequestData {
    [k: string]: string;
}

export function fetchRelatedSuggestions(
    field: string,
    selectedFields: { [k: string]: Suggestion | null },
): Promise<RelatedSuggestion[]> {
    const data: RequestData = Object.keys(selectedFields).reduce((res: RequestData, k: string) => {
        res[k] = selectedFields[k]!.id;
        return res;
    }, {});

    return axios
        .get('/related', { params: { f: field, q: data } })
        .then(res => {
            console.log(res);
            return res.data || [];
        })
        .catch(err => {
            console.log(err);
            return relatedExample;
        });
}
