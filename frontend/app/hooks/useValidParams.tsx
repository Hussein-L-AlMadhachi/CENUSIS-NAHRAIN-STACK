

export function useValidParams(data: Record<string, any>, valid_params: string[]) {
    const keysSet = new Set(Object.keys(data));

    for (const key of valid_params) {
        if (!keysSet.has(key) && data[key]) {
            throw new Error("يجب ملئ كل الحقول" + key);
        }
    }
}


